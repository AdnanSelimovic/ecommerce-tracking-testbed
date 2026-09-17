<?php

namespace App\Tracking;

use App\Enums\GroundTruthEventName;

class MetaClientEventMapper
{
    /** @return array{name: string, params: array<string, mixed>, options: array{eventID: string}} */
    public function map(ClientTrackingPayload $payload): array
    {
        $params = array_filter([
            'value' => $payload->value,
            'currency' => $payload->currency,
            'content_ids' => array_column($payload->items, 'item_id'),
            'content_type' => 'product',
            'contents' => array_map(static fn (array $item): array => [
                'id' => $item['item_id'],
                'quantity' => $item['quantity'],
                'item_price' => $item['price'],
            ], $payload->items),
            'order_id' => $payload->transactionId,
        ], static fn (mixed $value): bool => $value !== null);

        if (in_array($payload->eventName, [GroundTruthEventName::BeginCheckout->value, GroundTruthEventName::Purchase->value], true)) {
            $params['num_items'] = $payload->quantity;
        }

        return [
            'name' => match ($payload->eventName) {
                GroundTruthEventName::ViewItem->value => 'ViewContent',
                GroundTruthEventName::AddToCart->value => 'AddToCart',
                GroundTruthEventName::BeginCheckout->value => 'InitiateCheckout',
                GroundTruthEventName::Purchase->value => 'Purchase',
            },
            'params' => $params,
            'options' => ['eventID' => $payload->groundTruthEventId],
        ];
    }
}
