<?php

namespace Tests\Feature;

use App\Enums\GroundTruthEventName;
use App\Models\GroundTruthEvent;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBrowsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_products_are_listed(): void
    {
        $active = Product::factory()->create(['name' => 'Visible Product']);
        $hidden = Product::factory()->inactive()->create(['name' => 'Hidden Product']);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee($active->name)
            ->assertDontSee($hidden->name);
    }

    public function test_a_product_detail_page_can_be_viewed(): void
    {
        $product = Product::factory()->create(['price_minor' => 12900]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('129.00 EUR');
    }

    public function test_an_inactive_product_detail_page_returns_404(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->get(route('products.show', $product))->assertNotFound();
    }

    public function test_viewing_a_product_records_a_view_item_ground_truth_event(): void
    {
        $product = Product::factory()->create(['price_minor' => 2450]);

        $this->get(route('products.show', $product))->assertOk();

        $event = GroundTruthEvent::sole();

        $this->assertSame(GroundTruthEventName::ViewItem, $event->event_name);
        $this->assertSame($product->id, $event->product_id);
        $this->assertSame(2450, $event->value_minor);
        $this->assertSame('EUR', $event->currency);
        $this->assertNotNull($event->occurred_at);
    }
}
