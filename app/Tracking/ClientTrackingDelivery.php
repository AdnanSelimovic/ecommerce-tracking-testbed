<?php

namespace App\Tracking;

use App\Models\GroundTruthEvent;

class ClientTrackingDelivery
{
    public function __construct(
        private readonly ClientTrackingEligibility $eligibility,
        private readonly ClientTrackingQueue $queue,
        private readonly ClientTrackingPayloadFactory $payloads,
        private readonly Ga4ClientEventMapper $ga4,
        private readonly MetaClientEventMapper $meta,
    ) {
    }

    public function queueIfEligible(GroundTruthEvent $event): void
    {
        if ($this->providersEnabled() && $this->eligibility->allows($event)) {
            $this->queue->queue($event);
        }
    }

    /** @return array{config: array<string, array<string, bool|string|null>>, events: array<int, array<string, mixed>>} */
    public function pullBrowserBootstrap(): array
    {
        $events = $this->queue->pull()
            ->map(function (GroundTruthEvent $event): array {
                $payload = $this->payloads->make($event);

                return [
                    'ground_truth_event_id' => $payload->groundTruthEventId,
                    'experiment_run_id' => $payload->experimentRunId,
                    'event_name' => $payload->eventName,
                    'value_minor' => $payload->valueMinor,
                    'occurred_at' => $payload->occurredAt,
                    'ga4' => $this->ga4->map($payload),
                    'meta' => $this->meta->map($payload),
                ];
            })
            ->all();

        return [
            'config' => [
                'ga4' => [
                    'enabled' => (bool) config('tracking.ga4.enabled'),
                    'measurement_id' => config('tracking.ga4.measurement_id'),
                ],
                'meta' => [
                    'enabled' => (bool) config('tracking.meta.enabled'),
                    'pixel_id' => config('tracking.meta.pixel_id'),
                ],
            ],
            'events' => $events,
        ];
    }

    private function providersEnabled(): bool
    {
        return (bool) config('tracking.ga4.enabled') || (bool) config('tracking.meta.enabled');
    }
}
