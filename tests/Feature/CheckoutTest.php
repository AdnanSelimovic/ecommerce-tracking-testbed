<?php

namespace Tests\Feature;

use App\Enums\GroundTruthEventName;
use App\Enums\OrderStatus;
use App\Models\GroundTruthEvent;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function fillCart(): array
    {
        $a = Product::factory()->create(['price_minor' => 12900]);
        $b = Product::factory()->create(['price_minor' => 2450]);

        $this->post(route('cart.store'), ['product_id' => $a->id, 'quantity' => 2]);
        $this->post(route('cart.store'), ['product_id' => $b->id, 'quantity' => 1]);

        return [$a, $b];
    }

    public function test_the_checkout_page_requires_a_non_empty_cart(): void
    {
        $this->get(route('checkout.show'))->assertRedirect(route('cart.index'));
        $this->post(route('checkout.store'))->assertRedirect(route('cart.index'));

        $this->assertSame(0, Order::count());
    }

    public function test_opening_checkout_records_a_begin_checkout_ground_truth_event(): void
    {
        $this->fillCart();

        $this->get(route('checkout.show'))->assertOk();

        $event = GroundTruthEvent::where('event_name', GroundTruthEventName::BeginCheckout->value)->sole();

        $this->assertSame(3, $event->quantity);
        $this->assertSame(28250, $event->value_minor);
        $this->assertSame('EUR', $event->currency);
    }

    public function test_checkout_creates_a_completed_order_with_correct_totals(): void
    {
        [$a, $b] = $this->fillCart();

        $this->post(route('checkout.store'))->assertRedirect();

        $order = Order::sole();

        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame('EUR', $order->currency);
        // (12900 * 2) + (2450 * 1)
        $this->assertSame(28250, $order->total_minor);
        $this->assertCount(2, $order->items);

        $first = $order->items->firstWhere('product_id', $a->id);
        $this->assertSame($a->name, $first->product_name);
        $this->assertSame(12900, $first->unit_price_minor);
        $this->assertSame(2, $first->quantity);
        $this->assertSame(25800, $first->line_total_minor);

        $this->assertSame(
            $order->total_minor,
            (int) $order->items->sum('line_total_minor')
        );

        $second = $order->items->firstWhere('product_id', $b->id);
        $this->assertSame(2450, $second->line_total_minor);
    }

    public function test_the_cart_is_emptied_after_checkout(): void
    {
        $this->fillCart();
        $this->post(route('checkout.store'));

        $this->get(route('cart.index'))->assertOk()->assertSee('Your cart is empty');
    }

    public function test_the_order_confirmation_page_can_be_viewed(): void
    {
        $this->fillCart();
        $this->post(route('checkout.store'));

        $order = Order::sole();

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('282.50 EUR');
    }
}
