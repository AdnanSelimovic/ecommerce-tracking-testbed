<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\GroundTruthRecorder;
use App\Tracking\ClientTrackingDelivery;
use Illuminate\Contracts\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('products.index', [
            'products' => Product::active()->orderBy('name')->get(),
        ]);
    }

    public function show(Product $product, GroundTruthRecorder $recorder, ClientTrackingDelivery $tracking): View
    {
        abort_unless($product->is_active, 404);

        $tracking->queueIfEligible($recorder->viewItem($product));

        return view('products.show', [
            'product' => $product,
        ]);
    }
}
