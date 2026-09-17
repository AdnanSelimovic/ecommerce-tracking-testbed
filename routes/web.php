<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ResearchDebugController;
use App\Http\Middleware\RestrictResearchControlToLocal;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::delete('/cart/{product}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

// This research control surface intentionally exists only in local/testing environments.
Route::middleware(RestrictResearchControlToLocal::class)->prefix('research')->group(function (): void {
    Route::get('/debug', [ResearchDebugController::class, 'index'])->name('research.debug');
    Route::post('/runs', [ResearchDebugController::class, 'start'])->name('research.runs.start');
    Route::post('/runs/{run}/finish', [ResearchDebugController::class, 'finish'])->name('research.runs.finish');
    Route::post('/context/clear', [ResearchDebugController::class, 'clear'])->name('research.context.clear');
});
