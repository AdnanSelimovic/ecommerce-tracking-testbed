<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\CartLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\GroundTruthEvent;
use App\Tracking\ClientTrackingDelivery;
use RuntimeException;

class OrderCreator
{
    public function __construct(
        private readonly GroundTruthRecorder $recorder,
        private readonly ClientTrackingDelivery $tracking,
    )
    {
    }

    /**
     * Create a completed synthetic order from the current cart.
     *
     * The order, its items and the canonical purchase event are written in a
     * single transaction so the ground truth can never be partially recorded.
     */
    public function createFromCart(Cart $cart): Order
    {
        $lines = $cart->lines();

        if ($lines->isEmpty()) {
            throw new RuntimeException('Cannot create an order from an empty cart.');
        }

        $purchaseEvent = null;

        $order = DB::transaction(function () use ($lines, &$purchaseEvent): Order {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'total_minor' => $lines->sum(fn (CartLine $line) => $line->lineTotalMinor()),
                'currency' => config('testbed.currency'),
                'status' => OrderStatus::Completed,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line->product->id,
                    'product_name' => $line->product->name,
                    'unit_price_minor' => $line->unitPriceMinor(),
                    'quantity' => $line->quantity,
                    'line_total_minor' => $line->lineTotalMinor(),
                ]);
            }

            $purchaseEvent = $this->recorder->purchase($order->load('items'));

            return $order;
        });

        /** @var GroundTruthEvent $purchaseEvent */
        $this->tracking->queueIfEligible($purchaseEvent);

        return $order;
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
