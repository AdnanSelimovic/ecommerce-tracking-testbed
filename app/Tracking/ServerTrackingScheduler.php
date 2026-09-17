<?php
namespace App\Tracking;
use App\Enums\ServerTrackingDispatchStatus; use App\Jobs\DispatchServerTracking; use App\Models\GroundTruthEvent; use App\Models\ServerTrackingDispatch;
class ServerTrackingScheduler
{
    public function __construct(private ServerTrackingEligibility $eligibility)
    {
    }

    public function schedule(GroundTruthEvent $event, array $requestContext = []): void
    {
        if (! $this->eligibility->allows($event)) return;

        foreach (['ga4', 'meta'] as $provider) {
            $target = $provider === 'ga4' ? config('tracking.ga4.server_measurement_id') : config('tracking.meta.pixel_id');
            $ready = $provider === 'ga4' ? config('tracking.ga4.server_enabled') : config('tracking.meta.capi_enabled');
            if (! $ready) continue;

            $dispatch = ServerTrackingDispatch::firstOrCreate(
                ['ground_truth_event_id' => $event->id, 'provider' => $provider],
                ['experiment_run_id' => $event->experiment_run_id, 'status' => ServerTrackingDispatchStatus::Pending, 'target_id' => $target, 'api_version' => $provider === 'meta' ? config('tracking.meta.graph_api_version') : null, 'queued_at' => now(), 'request_context' => $requestContext]
            );

            if ($dispatch->wasRecentlyCreated) DispatchServerTracking::dispatch($dispatch->id)->onQueue('tracking')->afterCommit();
        }
    }
}
