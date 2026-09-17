<?php

namespace App\Tracking;

use App\Models\GroundTruthEvent;

class ClientTrackingPayloadFactory
{
    public function make(GroundTruthEvent $event): ClientTrackingPayload
    {
        $event->loadMissing(['experimentRun', 'product', 'order.items']);

        $items = $event->payload['items'] ?? $this->singleProductItem($event);

        return new ClientTrackingPayload(
            groundTruthEventId: $event->event_id,
            experimentRunId: $event->experimentRun?->run_id,
            eventName: $event->event_name->value,
            items: array_map(fn (array $item): array => $this->mapItem($item), $items),
            valueMinor: $event->value_minor,
            value: $event->value_minor === null ? null : $this->decimal($event->value_minor),
            currency: $event->currency,
            transactionId: $event->order?->order_number ?? ($event->payload['order_number'] ?? null),
            quantity: $event->quantity ?? 0,
            occurredAt: $event->occurred_at->toIso8601String(),
        );
    }

    /** @return array<int, array<string, int|string>> */
    private function singleProductItem(GroundTruthEvent $event): array
    {
        if ($event->product === null) {
            return [];
        }

        return [[
            'product_id' => $event->product->id,
            'product_name' => $event->product->name,
            'unit_price_minor' => $event->product->price_minor,
            'quantity' => $event->quantity ?? 1,
        ]];
    }

    /** @param array<string, mixed> $item
     *  @return array{item_id: string, item_name: string, price: float, quantity: int}
     */
    private function mapItem(array $item): array
    {
        return [
            'item_id' => (string) $item['product_id'],
            'item_name' => (string) ($item['product_name'] ?? $item['product_slug'] ?? $item['product_id']),
            'price' => $this->decimal((int) $item['unit_price_minor']),
            'quantity' => (int) $item['quantity'],
        ];
    }

    private function decimal(int $minor): float
    {
        return (float) number_format($minor / 100, 2, '.', '');
    }
}
