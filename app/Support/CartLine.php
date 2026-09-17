<?php

namespace App\Support;

use App\Models\Product;

final class CartLine
{
    public function __construct(
        public readonly Product $product,
        public readonly int $quantity,
    ) {
    }

    public function unitPriceMinor(): int
    {
        return $this->product->price_minor;
    }

    public function lineTotalMinor(): int
    {
        return $this->product->price_minor * $this->quantity;
    }
}
