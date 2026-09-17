@extends('layouts.app')

@section('title', $product->name)

@section('content')
    <p class="muted"><a href="{{ route('products.index') }}">&larr; Back to products</a></p>

    <h1>{{ $product->name }}</h1>

    <div class="card">
        <p class="price">{{ \App\Support\Money::format($product->price_minor) }}</p>
        <p>{{ $product->description }}</p>

        <form method="POST" action="{{ route('cart.store') }}" data-testid="add-to-cart-form">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <label for="quantity" class="muted">Quantity</label>
            <input id="quantity" type="number" name="quantity" value="1" min="1" max="10">
            <button type="submit" data-testid="add-to-cart">Add to cart</button>
        </form>
    </div>

    <p class="muted">SKU slug: <code>{{ $product->slug }}</code></p>
@endsection
