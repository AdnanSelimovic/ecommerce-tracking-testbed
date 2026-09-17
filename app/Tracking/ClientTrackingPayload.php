<?php

namespace App\Tracking;

/**
 * Provider-neutral, browser-safe representation of one ground-truth event.
 * Monetary amounts are decimal only at this tracking presentation boundary.
 *
 * @phpstan-type TrackingItem array{item_id: string, item_name: string, price: float, quantity: int}
 */
final readonly class ClientTrackingPayload
{
    /** @param array<int, array{item_id: string, item_name: string, price: float, quantity: int}> $items */
    public function __construct(
        public string $groundTruthEventId,
        public ?string $experimentRunId,
        public string $eventName,
        public array $items,
        public ?int $valueMinor,
        public ?float $value,
        public ?string $currency,
        public ?string $transactionId,
        public int $quantity,
        public string $occurredAt,
    ) {
    }
}
