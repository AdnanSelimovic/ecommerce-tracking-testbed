<?php

namespace App\Jobs;

use App\Enums\ServerTrackingDispatchStatus;
use App\Models\ServerTrackingAttempt;
use App\Models\ServerTrackingDispatch;
use App\Tracking\Ga4ServerPayloadFactory;
use App\Tracking\MetaServerPayloadFactory;
use App\Tracking\RetryableTrackingException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class DispatchServerTracking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 30, 120];

    public function __construct(public int $dispatchId) {}

    public function handle(Ga4ServerPayloadFactory $ga4, MetaServerPayloadFactory $meta): void
    {
        $dispatch = ServerTrackingDispatch::with('groundTruthEvent.experimentRun')->findOrFail($this->dispatchId);
        if ($dispatch->status === ServerTrackingDispatchStatus::EndpointAccepted) return;
        $attempt = ServerTrackingAttempt::create(['server_tracking_dispatch_id' => $dispatch->id, 'attempt_number' => $dispatch->attempt_count + 1, 'started_at' => now(), 'outcome' => 'processing']);
        $dispatch->update(['status' => ServerTrackingDispatchStatus::Processing, 'attempt_count' => $attempt->attempt_number, 'first_attempted_at' => $dispatch->first_attempted_at ?? now()]);
        $started = microtime(true);
        try {
            if ($dispatch->provider === 'ga4') {
                $payload = $ga4->make($dispatch->groundTruthEvent);
                $url = config('tracking.ga4.server_endpoint').'?measurement_id='.rawurlencode($dispatch->target_id).'&api_secret='.rawurlencode(config('tracking.ga4.server_api_secret'));
                $response = Http::connectTimeout(3)->timeout(10)->post($url, $payload);
            } else {
                $payload = $meta->make($dispatch->groundTruthEvent, ($dispatch->request_context ?? []) + ['test_event_code' => config('tracking.meta.capi_test_event_code')]);
                $url = 'https://graph.facebook.com/'.($dispatch->api_version ?? config('tracking.meta.graph_api_version')).'/'.$dispatch->target_id.'/events';
                $response = Http::connectTimeout(3)->timeout(10)->post($url, ['access_token' => config('tracking.meta.capi_access_token')] + $payload);
            }
            $body = $response->json() ?? [];
            $accepted = $dispatch->provider === 'ga4' ? $response->successful() : ($response->successful() && (($body['events_received'] ?? 0) >= 1));
            $outcome = $accepted ? 'endpoint_accepted' : (($response->status() === 429 || $response->status() >= 500) ? 'http_error' : 'provider_rejected');
            $attempt->update(['finished_at' => now(), 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'http_status' => $response->status(), 'outcome' => $outcome, 'response_payload' => $body]);
            $dispatch->update(['request_payload' => $payload, 'response_payload' => $body, 'last_http_status' => $response->status(), 'status' => $accepted ? ServerTrackingDispatchStatus::EndpointAccepted : ServerTrackingDispatchStatus::Failed, 'accepted_at' => $accepted ? now() : null, 'failed_at' => $accepted ? null : now()]);
            if ($outcome === 'http_error') throw new RetryableTrackingException('Transient provider HTTP '.$response->status());
        } catch (RetryableTrackingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $attempt->update(['finished_at' => now(), 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'outcome' => 'connection_error', 'error_message' => $exception->getMessage()]);
            $dispatch->update(['status' => ServerTrackingDispatchStatus::Failed, 'failed_at' => now(), 'last_error_code' => class_basename($exception), 'last_error_message' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
