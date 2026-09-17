<?php

namespace App\Http\Controllers;

use App\Services\Cart;
use App\Services\GroundTruthRecorder;
use App\Services\OrderCreator;
use App\Tracking\TrackingCoordinator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CheckoutController extends Controller
{
    public function show(Cart $cart, GroundTruthRecorder $recorder, TrackingCoordinator $tracking): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()->route('cart.index')->with('status', 'Your cart is empty.');
        }

        $lines = $cart->lines();
        $totalMinor = $cart->totalMinor();

        $tracking->handle($recorder->beginCheckout($lines, $totalMinor));

        return view('checkout.show', [
            'lines' => $lines,
            'totalMinor' => $totalMinor,
        ]);
    }

    public function store(Cart $cart, OrderCreator $orders): RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()->route('cart.index')->with('status', 'Your cart is empty.');
        }

        $order = $orders->createFromCart($cart);

        $cart->clear();

        // Post/Redirect/Get: the confirmation page is a plain GET and never
        // creates an order or a purchase event.
        return redirect()->route('orders.show', $order);
    }
}
