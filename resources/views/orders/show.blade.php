@extends('layouts.app')

@section('title', 'Order '.$order->order_number)

@section('content')
    <h1>Order confirmed</h1>

    <div class="card">
        <p>
            Order number: <code>{{ $order->order_number }}</code><br>
            Status: {{ $order->status->value }}<br>
            Total: <span class="price">{{ \App\Support\Money::format($order->total_minor, $order->currency) }}</span>
        </p>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="num">Unit price</th>
                    <th class="num">Qty</th>
                    <th class="num">Line total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td class="num">{{ \App\Support\Money::format($item->unit_price_minor, $order->currency) }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ \App\Support\Money::format($item->line_total_minor, $order->currency) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="muted">Refreshing this page does not create another order or another purchase event.</p>

    <a class="btn secondary" href="{{ route('products.index') }}">Back to products</a>
@endsection
