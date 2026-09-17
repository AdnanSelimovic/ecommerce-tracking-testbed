<?php

namespace App\Services;

use App\Models\Product;
use App\Support\CartLine;
use Illuminate\Support\Collection;

/**
 * Session-backed synthetic cart.
 *
 * The session only stores product ids and quantities; prices are always read
 * back from the database so the cart can never drift from the ground truth.
 */
class Cart
{
    private const SESSION_KEY = 'cart.items';

    /**
     * @return array<int, int> product id => quantity
     */
    public function rawItems(): array
    {
        return session(self::SESSION_KEY, []);
    }

    public function add(Product $product, int $quantity = 1): void
    {
        $items = $this->rawItems();
        $items[$product->id] = (int) ($items[$product->id] ?? 0) + $quantity;

        session([self::SESSION_KEY => $items]);
    }

    public function remove(Product $product): void
    {
        $items = $this->rawItems();
        unset($items[$product->id]);

        session([self::SESSION_KEY => $items]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function isEmpty(): bool
    {
        return $this->rawItems() === [];
    }

    /**
     * @return Collection<int, CartLine>
     */
    public function lines(): Collection
    {
        $items = $this->rawItems();

        if ($items === []) {
            return collect();
        }

        $products = Product::whereIn('id', array_keys($items))->get()->keyBy('id');

        return collect($items)
            ->filter(fn ($quantity, $productId) => $products->has((int) $productId) && (int) $quantity > 0)
            ->map(fn ($quantity, $productId) => new CartLine($products->get((int) $productId), (int) $quantity))
            ->values();
    }

    public function totalMinor(): int
    {
        return $this->lines()->sum(fn (CartLine $line) => $line->lineTotalMinor());
    }

    public function totalQuantity(): int
    {
        return $this->lines()->sum(fn (CartLine $line) => $line->quantity);
    }
}
