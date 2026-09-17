<?php
namespace App\Tracking;
use App\Models\GroundTruthEvent;
class MetaServerPayloadFactory
{
    public function __construct(private ClientTrackingPayloadFactory $base, private MetaClientEventMapper $mapper) {}
    public function make(GroundTruthEvent $event, array $context = []): array
    {
        $mapped = $this->mapper->map($this->base->make($event));
        return ['data' => [array_filter(['event_name' => $mapped['name'], 'event_time' => $event->occurred_at->timestamp, 'event_id' => $event->event_id, 'action_source' => 'website', 'event_source_url' => $context['event_source_url'] ?? null, 'referrer_url' => $context['referrer_url'] ?? null, 'user_data' => array_filter(['client_user_agent' => $context['client_user_agent'] ?? null, 'fbp' => $context['fbp'] ?? null, 'fbc' => $context['fbc'] ?? null]), 'custom_data' => $mapped['params']], static fn ($value) => $value !== null)], 'test_event_code' => $context['test_event_code'] ?? null];
    }
}
