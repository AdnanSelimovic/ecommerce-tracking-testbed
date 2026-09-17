@extends('layouts.app')

@section('title', 'Cart')

@section('content')
    <h1>Cart</h1>

    @if ($lines->isEmpty())
        <p class="muted">Your cart is empty. <a href="{{ route('products.index') }}">Browse products</a>.</p>
    @else
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="num">Unit price</th>
                        <th class="num">Qty</th>
                        <th class="num">Line total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td>{{ $line->product->name }}</td>
                            <td class="num">{{ \App\Support\Money::format($line->unitPriceMinor()) }}</td>
                            <td class="num">{{ $line->quantity }}</td>
                            <td class="num">{{ \App\Support\Money::format($line->lineTotalMinor()) }}</td>
                            <td>
                                <form method="POST" action="{{ route('cart.destroy', $line->product) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="secondary">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3">Total</th>
                        <th class="num">{{ \App\Support\Money::format($totalMinor) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <a class="btn" href="{{ route('checkout.show') }}" data-testid="proceed-to-checkout">Proceed to checkout</a>
    @endif
@endsection
