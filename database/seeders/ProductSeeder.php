<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Fixed synthetic catalogue.
     *
     * Prices are deliberately stable so experiment runs stay reproducible and
     * comparable across measurement conditions.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Testbed Wireless Headphones',
                'slug' => 'testbed-wireless-headphones',
                'description' => 'Synthetic catalogue item used for tracking experiments.',
                'price_minor' => 12900,
            ],
            [
                'name' => 'Testbed Mechanical Keyboard',
                'slug' => 'testbed-mechanical-keyboard',
                'description' => 'Synthetic catalogue item used for tracking experiments.',
                'price_minor' => 8450,
            ],
            [
                'name' => 'Testbed USB-C Hub',
                'slug' => 'testbed-usb-c-hub',
                'description' => 'Synthetic catalogue item used for tracking experiments.',
                'price_minor' => 3999,
            ],
            [
                'name' => 'Testbed Desk Lamp',
                'slug' => 'testbed-desk-lamp',
                'description' => 'Synthetic catalogue item used for tracking experiments.',
                'price_minor' => 2450,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['slug' => $product['slug']],
                $product + ['is_active' => true],
            );
        }
    }
}
