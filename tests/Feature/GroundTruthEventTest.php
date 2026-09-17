<?php

namespace Tests\Feature;

use App\Enums\GroundTruthEventName;
use App\Enums\ExperimentRunStatus;
use App\Models\ExperimentRun;
use App\Models\GroundTruthEvent;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GroundTruthEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_ground_truth_event_receives_a_unique_uuid(): void
    {
        $product = Product::factory()->create();

        $this->get(route('products.show', $product));
        $this->get(route('products.show', $product));

        $events = GroundTruthEvent::all();

        $this->assertCount(2, $events);

        foreach ($events as $event) {
            $this->assertTrue(Str::isUuid($event->event_id));
        }

        $this->assertCount(2, $events->pluck('event_id')->unique());
    }

    public function test_an_experiment_run_receives_a_unique_uuid(): void
    {
        $run = ExperimentRun::create([]);

        $this->assertTrue(Str::isUuid($run->run_id));
        $this->assertSame(ExperimentRunStatus::Pending, $run->status);
    }

    public function test_a_successful_order_creates_exactly_one_purchase_event(): void
    {
        $product = Product::factory()->create(['price_minor' => 5000]);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);
        $this->post(route('checkout.store'));

        $order = Order::sole();

        $purchases = GroundTruthEvent::where('event_name', GroundTruthEventName::Purchase->value)->get();

        $this->assertCount(1, $purchases);
        $this->assertSame($order->id, $purchases->first()->order_id);
        $this->assertSame(10000, $purchases->first()->value_minor);
        $this->assertSame(2, $purchases->first()->quantity);
    }

    public function test_refreshing_the_confirmation_page_does_not_create_another_order_or_purchase_event(): void
    {
        $product = Product::factory()->create(['price_minor' => 5000]);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->post(route('checkout.store'));

        $order = Order::sole();

        $this->get(route('orders.show', $order))->assertOk();
        $this->get(route('orders.show', $order))->assertOk();
        $this->get(route('orders.show', $order))->assertOk();

        $this->assertSame(1, Order::count());
        $this->assertSame(
            1,
            GroundTruthEvent::where('event_name', GroundTruthEventName::Purchase->value)->count()
        );
    }

    public function test_the_research_debug_page_lists_recent_events(): void
    {
        $product = Product::factory()->create();

        $this->get(route('products.show', $product));

        $event = GroundTruthEvent::sole();

        $this->get(route('research.debug'))
            ->assertOk()
            ->assertSee($event->event_id)
            ->assertSee('view_item')
            ->assertSee($product->name);
    }
}
