<?php

namespace App\Services;

use App\Enums\GroundTruthEventName;
use App\Models\GroundTruthEvent;
use App\Models\Order;
use App\Models\Product;
use App\Support\CartLine;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

/**
 * Records canonical backend ecommerce events.
 *
 * Laravel is the source of truth in this research: every recorded event here
 * happened server-side, independently of GA4, Meta Pixel or any other
 * measurement system added in later milestones.
 */
class GroundTruthRecorder
{
    public function __construct(private readonly ExperimentRunContext $experimentContext)
    {
    }

    public function viewItem(Product $product): GroundTruthEvent
    {
        return $this->record(GroundTruthEventName::ViewItem, [
            'product_id' => $product->id,
            'quantity' => 1,
            'value_minor' => $product->price_minor,
            'payload' => [
                'product_slug' => $product->slug,
                'product_name' => $product->name,
            ],
        ]);
    }

    public function addToCart(Product $product, int $quantity): GroundTruthEvent
    {
        return $this->record(GroundTruthEventName::AddToCart, [
            'product_id' => $product->id,
            'quantity' => $quantity,
            'value_minor' => $product->price_minor * $quantity,
            'payload' => [
                'product_slug' => $product->slug,
                'product_name' => $product->name,
                'unit_price_minor' => $product->price_minor,
            ],
        ]);
    }

    /**
     * @param  Collection<int, CartLine>  $lines
     */
    public function beginCheckout(Collection $lines, int $totalMinor): GroundTruthEvent
    {
        return $this->record(GroundTruthEventName::BeginCheckout, [
            'quantity' => (int) $lines->sum(fn (CartLine $line) => $line->quantity),
            'value_minor' => $totalMinor,
            'payload' => [
                'items' => $lines->map(fn (CartLine $line) => [
                    'product_id' => $line->product->id,
                    'product_slug' => $line->product->slug,
                    'quantity' => $line->quantity,
                    'unit_price_minor' => $line->unitPriceMinor(),
                    'line_total_minor' => $line->lineTotalMinor(),
                ])->all(),
            ],
        ]);
    }

    /**
     * Idempotent per order: an order can only ever produce one purchase event.
     */
    public function purchase(Order $order): GroundTruthEvent
    {
        $existing = GroundTruthEvent::where('event_name', GroundTruthEventName::Purchase->value)
            ->where('order_id', $order->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return GroundTruthEvent::create([
                'event_name' => GroundTruthEventName::Purchase->value,
                'order_id' => $order->id,
                'experiment_run_id' => $this->currentExperimentRunId(),
                'quantity' => (int) $order->items->sum('quantity'),
                'value_minor' => $order->total_minor,
                'currency' => $order->currency,
                'occurred_at' => now(),
                'payload' => [
                    'order_number' => $order->order_number,
                    'items' => $order->items->map(fn ($item) => [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'unit_price_minor' => $item->unit_price_minor,
                        'line_total_minor' => $item->line_total_minor,
                    ])->all(),
                ],
            ]);
        } catch (QueryException $exception) {
            $existing = GroundTruthEvent::where('event_name', GroundTruthEventName::Purchase->value)
                ->where('order_id', $order->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(GroundTruthEventName $name, array $attributes = []): GroundTruthEvent
    {
        return GroundTruthEvent::create(array_merge([
            'event_name' => $name->value,
            'experiment_run_id' => $this->currentExperimentRunId(),
            'currency' => config('testbed.currency'),
            'occurred_at' => now(),
        ], $attributes));
    }

    /**
     * The browser/session context is intentionally isolated from this recorder.
     */
    protected function currentExperimentRunId(): ?int
    {
        return $this->experimentContext->current()?->getKey();
    }
}
