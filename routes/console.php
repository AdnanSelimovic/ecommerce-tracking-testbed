<?php

use App\Models\GroundTruthEvent;
use App\Tracking\Ga4ServerPayloadFactory;
use Illuminate\Support\Facades\Http;

Artisan::command('tracking:validate-ga4-server {eventId}', function (string $eventId, Ga4ServerPayloadFactory $payloads): int {
    if (! config('tracking.ga4.server_enabled')) {
        $this->error('GA4 server Measurement Protocol is not configured.');
        return self::FAILURE;
    }
    $event = GroundTruthEvent::with('experimentRun')->where('event_id', $eventId)->firstOrFail();
    $url = config('tracking.ga4.server_debug_endpoint').'?measurement_id='.rawurlencode(config('tracking.ga4.server_measurement_id')).'&api_secret='.rawurlencode(config('tracking.ga4.server_api_secret'));
    $response = Http::timeout(10)->post($url, $payloads->make($event)+['validation_behavior'=>'ENFORCE_RECOMMENDATIONS']);
    $this->line($response->body());
    return $response->successful() ? self::SUCCESS : self::FAILURE;
})->purpose('Validate one GA4 server payload without changing dispatch evidence');

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
