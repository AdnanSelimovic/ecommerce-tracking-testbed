<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToCartRequest;
use App\Models\Product;
use App\Services\Cart;
use App\Services\GroundTruthRecorder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CartController extends Controller
{
    public function index(Cart $cart): View
    {
        return view('cart.index', [
            'lines' => $cart->lines(),
            'totalMinor' => $cart->totalMinor(),
        ]);
    }

    public function store(AddToCartRequest $request, Cart $cart, GroundTruthRecorder $recorder): RedirectResponse
    {
        $product = Product::active()->findOrFail($request->validated()['product_id']);
        $quantity = $request->quantity();

        $cart->add($product, $quantity);

        $recorder->addToCart($product, $quantity);

        return redirect()
            ->route('cart.index')
            ->with('status', sprintf('Added %d x %s to the cart.', $quantity, $product->name));
    }

    public function destroy(Product $product, Cart $cart): RedirectResponse
    {
        $cart->remove($product);

        return redirect()->route('cart.index');
    }
}
