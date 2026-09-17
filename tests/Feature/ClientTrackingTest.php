<?php

namespace Tests\Feature;

use App\Enums\GroundTruthEventName;
use App\Models\ExperimentRun;
use App\Models\GroundTruthEvent;
use App\Models\Order;
use App\Models\Product;
use App\Tracking\ClientTrackingEligibility;
use App\Tracking\ClientTrackingPayloadFactory;
use App\Tracking\Ga4ClientEventMapper;
use App\Tracking\MetaClientEventMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function enableProviders(): void
    {
        config()->set('tracking.ga4.enabled', true);
        config()->set('tracking.ga4.measurement_id', 'G-TESTBED123');
        config()->set('tracking.meta.enabled', true);
        config()->set('tracking.meta.pixel_id', '123456789');
    }

    private function startRun(string $trackingMode = 'client_only', string $consentMode = 'full'): ExperimentRun
    {
        $this->post(route('research.runs.start'), [
            'tracking_mode' => $trackingMode,
            'consent_mode' => $consentMode,
        ])->assertRedirect(route('research.debug'));

        return ExperimentRun::latest('id')->firstOrFail();
    }

    public function test_tracking_is_disabled_cleanly_when_provider_ids_are_missing(): void
    {
        config()->set('tracking.ga4.enabled', false);
        config()->set('tracking.ga4.measurement_id', null);
        config()->set('tracking.meta.enabled', false);
        config()->set('tracking.meta.pixel_id', null);
        $this->startRun();
        $product = Product::factory()->create();

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertDontSee('testbedClientTracking');
    }

    public function test_providers_can_be_enabled_independently(): void
    {
        config()->set('tracking.ga4.enabled', true);
        config()->set('tracking.ga4.measurement_id', 'G-TESTBED123');
        config()->set('tracking.meta.enabled', false);
        config()->set('tracking.meta.pixel_id', null);
        $this->startRun();
        $product = Product::factory()->create();

        $response = $this->get(route('products.show', $product));

        $response->assertOk()->assertSee('G-TESTBED123');
        $this->assertStringContainsString('"enabled":false', $response->getContent());
    }

    public function test_payload_maps_all_canonical_events_with_the_original_ids_and_money(): void
    {
        $this->enableProviders();
        $run = $this->startRun();
        $product = Product::factory()->create(['name' => 'Research Widget', 'price_minor' => 1234]);

        $this->get(route('products.show', $product));
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);
        $this->get(route('cart.index'));
        $this->get(route('checkout.show'));
        $this->post(route('checkout.store'));

        $events = GroundTruthEvent::with(['experimentRun', 'product', 'order.items'])->orderBy('id')->get();
        $factory = app(ClientTrackingPayloadFactory::class);
        $ga4 = app(Ga4ClientEventMapper::class);
        $meta = app(MetaClientEventMapper::class);

        $expectedMetaNames = ['ViewContent', 'AddToCart', 'InitiateCheckout', 'Purchase'];

        foreach ($events as $index => $event) {
            $payload = $factory->make($event);
            $ga4Event = $ga4->map($payload);
            $metaEvent = $meta->map($payload);

            $this->assertSame($event->event_id, $payload->groundTruthEventId);
            $this->assertSame($run->run_id, $payload->experimentRunId);
            $this->assertSame($event->event_name->value, $ga4Event['name']);
            $this->assertSame($event->event_id, $ga4Event['params']['testbed_event_id']);
            $this->assertSame($run->run_id, $ga4Event['params']['testbed_run_id']);
            $this->assertSame('client', $ga4Event['params']['tracking_channel']);
            $this->assertSame($expectedMetaNames[$index], $metaEvent['name']);
            $this->assertSame($event->event_id, $metaEvent['options']['eventID']);
        }

        $addToCart = $events->firstWhere('event_name', GroundTruthEventName::AddToCart);
        $addPayload = $factory->make($addToCart);
        $this->assertSame(24.68, $addPayload->value);
        $this->assertSame(12.34, $addPayload->items[0]['price']);

        $purchase = $events->firstWhere('event_name', GroundTruthEventName::Purchase);
        $purchaseGa4 = $ga4->map($factory->make($purchase));
        $this->assertSame(Order::sole()->order_number, $purchaseGa4['params']['transaction_id']);
        $this->assertSame($purchase->event_id, $purchaseGa4['params']['testbed_event_id']);
    }

    public function test_eligibility_accepts_full_consent_client_and_server_augmented_runs_only(): void
    {
        $eligibility = app(ClientTrackingEligibility::class);

        $clientOnly = ExperimentRun::create(['tracking_mode' => 'client_only', 'consent_mode' => 'full']);
        $serverAugmented = ExperimentRun::create(['tracking_mode' => 'server_augmented', 'consent_mode' => 'full']);
        $partial = ExperimentRun::create(['tracking_mode' => 'client_only', 'consent_mode' => 'partial']);
        $none = ExperimentRun::create(['tracking_mode' => 'client_only', 'consent_mode' => 'none']);
        $missing = ExperimentRun::create(['consent_mode' => 'full']);

        $this->assertTrue($eligibility->allowsRun($clientOnly));
        $this->assertTrue($eligibility->allowsRun($serverAugmented));
        $this->assertFalse($eligibility->allowsRun($partial));
        $this->assertFalse($eligibility->allowsRun($none));
        $this->assertFalse($eligibility->allowsRun($missing));
    }

    public function test_no_run_partial_and_no_consent_do_not_render_browser_tracking(): void
    {
        $this->enableProviders();
        $product = Product::factory()->create();

        $this->get(route('products.show', $product))->assertDontSee('testbedClientTracking');

        $this->startRun(consentMode: 'partial');
        $this->get(route('products.show', $product))->assertDontSee('testbedClientTracking');

        $this->post(route('research.context.clear'));
        $this->startRun(consentMode: 'none');
        $this->get(route('products.show', $product))->assertDontSee('testbedClientTracking');
    }

    public function test_add_to_cart_is_rendered_once_after_redirect_and_not_on_cart_refresh(): void
    {
        $this->enableProviders();
        $this->startRun();
        $product = Product::factory()->create();

        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $event = GroundTruthEvent::where('event_name', GroundTruthEventName::AddToCart->value)->sole();

        $this->get(route('cart.index'))->assertSee($event->event_id);
        $this->get(route('cart.index'))->assertDontSee($event->event_id);
    }

    public function test_purchase_is_rendered_once_after_redirect_and_not_on_confirmation_refresh(): void
    {
        $this->enableProviders();
        $this->startRun();
        $product = Product::factory()->create(['price_minor' => 5000]);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->get(route('cart.index'));
        $this->post(route('checkout.store'));

        $order = Order::sole();
        $event = GroundTruthEvent::where('event_name', GroundTruthEventName::Purchase->value)->sole();

        $this->get(route('orders.show', $order))->assertSee($event->event_id);
        $this->get(route('orders.show', $order))->assertDontSee($event->event_id);
    }

    public function test_same_request_view_item_and_begin_checkout_render_correlated_payloads(): void
    {
        $this->enableProviders();
        $this->startRun();
        $product = Product::factory()->create();

        $this->get(route('products.show', $product))
            ->assertSee(GroundTruthEvent::where('event_name', GroundTruthEventName::ViewItem->value)->sole()->event_id);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->get(route('cart.index'));
        $checkout = $this->get(route('checkout.show'));
        $checkout->assertSee(GroundTruthEvent::where('event_name', GroundTruthEventName::BeginCheckout->value)->sole()->event_id);
    }
}
