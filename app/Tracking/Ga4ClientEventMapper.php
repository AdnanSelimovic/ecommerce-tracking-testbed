<?php

namespace App\Tracking;

use App\Enums\GroundTruthEventName;

class Ga4ClientEventMapper
{
    /** @return array{name: string, params: array<string, mixed>} */
    public function map(ClientTrackingPayload $payload): array
    {
        $params = array_filter([
            'currency' => $payload->currency,
            'value' => $payload->value,
            'items' => $payload->items,
            'testbed_event_id' => $payload->groundTruthEventId,
            'testbed_run_id' => $payload->experimentRunId,
            'tracking_channel' => 'client',
        ], static fn (mixed $value): bool => $value !== null);

        if ($payload->eventName === GroundTruthEventName::Purchase->value) {
            $params['transaction_id'] = $payload->transactionId;
        }

        return [
            'name' => $payload->eventName,
            'params' => $params,
        ];
    }
}
