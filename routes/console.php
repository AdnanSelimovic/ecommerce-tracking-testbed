<?php

use App\Models\GroundTruthEvent;
use App\Tracking\Ga4ServerPayloadFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

Artisan::command('tracking:validate-ga4-server {eventId}', function (string $eventId, Ga4ServerPayloadFactory $payloads): int {
    if (! config('tracking.ga4.server_enabled')) {
        $this->error('GA4 server Measurement Protocol is not configured.');
        return self::FAILURE;
    }
    $event = GroundTruthEvent::with('experimentRun')->where('event_id', $eventId)->firstOrFail();
    $url = config('tracking.ga4.server_debug_endpoint').'?measurement_id='.rawurlencode(config('tracking.ga4.server_measurement_id')).'&api_secret='.rawurlencode(config('tracking.ga4.server_api_secret'));
    try {
        $response = Http::timeout(10)->post($url, $payloads->make($event)+['validation_behavior'=>'ENFORCE_RECOMMENDATIONS']);
    } catch (ConnectionException) {
        $this->error('GA4 debug endpoint connection failed.');

        return self::FAILURE;
    }

    $this->line($response->body());

    if (! $response->successful()) {
        return self::FAILURE;
    }

    $messages = $response->json('validationMessages');
    if (! is_array($messages) || $messages !== []) {
        $this->error('GA4 payload has validation messages.');

        return self::FAILURE;
    }

    return self::SUCCESS;
})->purpose('Validate one GA4 server payload without changing dispatch evidence');

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
