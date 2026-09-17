@extends('layouts.app')

@section('title', 'Products')

@section('content')
    <h1>Products</h1>

    @if ($products->isEmpty())
        <p class="muted">No active products. Run <code>php artisan db:seed</code>.</p>
    @else
        <div class="grid">
            @foreach ($products as $product)
                <div class="card">
                    <h2><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h2>
                    <p class="price">{{ \App\Support\Money::format($product->price_minor) }}</p>
                    <p class="muted">{{ $product->description }}</p>
                </div>
            @endforeach
        </div>
    @endif
@endsection
