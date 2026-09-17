<?php

namespace Tests\Feature;

use App\Enums\ExperimentRunStatus;
use App\Enums\GroundTruthEventName;
use App\Models\ExperimentRun;
use App\Models\GroundTruthEvent;
use App\Models\Order;
use App\Models\Product;
use App\Services\ExperimentRunContext;
use App\Services\ExperimentRunManager;
use App\Services\GroundTruthRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class ExperimentRunLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_run_is_created_with_a_uuid_and_known_conditions(): void
    {
        $run = app(ExperimentRunManager::class)->create([
            'tracking_mode' => 'client_only',
            'blocking_mode' => 'controlled',
            'privacy_mode' => 'standard',
            'consent_mode' => 'full',
            'browser' => 'Chrome',
            'browser_version' => '140',
            'metadata' => ['operator' => 'test'],
        ]);

        $this->assertTrue(Str::isUuid($run->run_id));
        $this->assertSame(ExperimentRunStatus::Pending, $run->status);
        $this->assertSame('client_only', $run->tracking_mode);
    }

    public function test_a_pending_run_can_start_and_a_running_run_can_complete(): void
    {
        $manager = app(ExperimentRunManager::class);
        $run = $manager->create();

        $manager->start($run);
        $this->assertSame(ExperimentRunStatus::Running, $run->status);
        $this->assertNotNull($run->started_at);

        $manager->complete($run);
        $this->assertSame(ExperimentRunStatus::Completed, $run->status);
        $this->assertNotNull($run->finished_at);
    }

    public function test_invalid_run_transitions_are_rejected(): void
    {
        $run = ExperimentRun::create([]);

        $this->expectException(LogicException::class);
        $run->transitionTo(ExperimentRunStatus::Completed);
    }

    public function test_context_binds_and_clears_the_current_running_run(): void
    {
        $run = app(ExperimentRunManager::class)->start(ExperimentRun::create([]));
        $context = app(ExperimentRunContext::class);

        $context->bind($run);
        $this->assertSame($run->id, $context->current()?->id);

        $context->clear();
        $this->assertNull($context->current());
    }

    public function test_all_ecommerce_events_in_a_bound_browser_flow_belong_to_the_same_run(): void
    {
        $product = Product::factory()->create(['price_minor' => 5000]);

        $this->post(route('research.runs.start'), ['tracking_mode' => 'client_only'])
            ->assertRedirect(route('research.debug'));

        $run = ExperimentRun::sole();
        $this->get(route('products.show', $product))->assertOk();
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);
        $this->get(route('checkout.show'))->assertOk();
        $this->post(route('checkout.store'))->assertRedirect();

        $events = GroundTruthEvent::orderBy('id')->get();

        $this->assertSame([
            GroundTruthEventName::ViewItem,
            GroundTruthEventName::AddToCart,
            GroundTruthEventName::BeginCheckout,
            GroundTruthEventName::Purchase,
        ], $events->pluck('event_name')->all());
        $this->assertCount(4, $events);
        $this->assertSame([$run->id], $events->pluck('experiment_run_id')->unique()->all());
    }

    public function test_events_without_a_bound_run_remain_valid_and_unattributed(): void
    {
        $product = Product::factory()->create();

        $this->get(route('products.show', $product));

        $this->assertNull(GroundTruthEvent::sole()->experiment_run_id);
    }

    public function test_finishing_a_browser_bound_run_clears_its_context(): void
    {
        $this->post(route('research.runs.start'));
        $run = ExperimentRun::sole();

        $this->post(route('research.runs.finish', $run))->assertRedirect(route('research.debug'));

        $this->assertSame(ExperimentRunStatus::Completed, $run->fresh()->status);
        $this->assertNull(app(ExperimentRunContext::class)->current());
    }

    public function test_database_constraint_prevents_duplicate_purchase_events_for_one_order(): void
    {
        $product = Product::factory()->create(['price_minor' => 5000]);
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->post(route('checkout.store'));

        $order = Order::sole();
        app(GroundTruthRecorder::class)->purchase($order->load('items'));
        $this->assertSame(1, GroundTruthEvent::where('order_id', $order->id)->count());

        $this->expectException(QueryException::class);
        GroundTruthEvent::create([
            'event_name' => GroundTruthEventName::Purchase->value,
            'order_id' => $order->id,
            'currency' => 'EUR',
        ]);
    }
}
