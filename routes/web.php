<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ResearchDebugController;
use App\Http\Controllers\ResearchAutomationController;
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

    Route::prefix('automation')->name('research.automation.')->group(function (): void {
        Route::get('/bootstrap', [ResearchAutomationController::class, 'bootstrap'])->name('bootstrap');
        Route::post('/runs', [ResearchAutomationController::class, 'create'])->name('runs.create');
        Route::get('/runs/{run}', [ResearchAutomationController::class, 'show'])->name('runs.show');
        Route::get('/runs/{run}/events', [ResearchAutomationController::class, 'events'])->name('runs.events');
        Route::post('/runs/{run}/observations', [ResearchAutomationController::class, 'observations'])->name('runs.observations');
        Route::post('/runs/{run}/complete', [ResearchAutomationController::class, 'complete'])->name('runs.complete');
        Route::post('/runs/{run}/fail', [ResearchAutomationController::class, 'fail'])->name('runs.fail');
    });
});
