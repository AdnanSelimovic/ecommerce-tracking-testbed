<?php

namespace Tests\Feature;

use App\Enums\GroundTruthEventName;
use App\Models\GroundTruthEvent;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_can_be_added_to_the_cart(): void
    {
        $product = Product::factory()->create(['price_minor' => 8450]);

        $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect(route('cart.index'));

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('169.00 EUR');
    }

    public function test_adding_the_same_product_twice_increases_the_quantity(): void
    {
        $product = Product::factory()->create(['price_minor' => 1000]);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 3]);

        $this->get(route('cart.index'))->assertOk()->assertSee('40.00 EUR');
    }

    public function test_add_to_cart_is_validated(): void
    {
        $this->post(route('cart.store'), ['product_id' => 999999])
            ->assertSessionHasErrors('product_id');
    }

    public function test_adding_to_the_cart_records_an_add_to_cart_ground_truth_event(): void
    {
        $product = Product::factory()->create(['price_minor' => 3999]);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 3]);

        $event = GroundTruthEvent::where('event_name', GroundTruthEventName::AddToCart->value)->sole();

        $this->assertSame($product->id, $event->product_id);
        $this->assertSame(3, $event->quantity);
        $this->assertSame(11997, $event->value_minor);
    }

    public function test_a_product_can_be_removed_from_the_cart(): void
    {
        $product = Product::factory()->create();

        $this->post(route('cart.store'), ['product_id' => $product->id]);
        $this->delete(route('cart.destroy', $product))->assertRedirect(route('cart.index'));

        $this->get(route('cart.index'))->assertOk()->assertSee('Your cart is empty');
    }
}
