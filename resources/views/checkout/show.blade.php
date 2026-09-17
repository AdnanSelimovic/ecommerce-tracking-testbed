@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
    <h1>Checkout</h1>

    <div class="card">
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
                @foreach ($lines as $line)
                    <tr>
                        <td>{{ $line->product->name }}</td>
                        <td class="num">{{ \App\Support\Money::format($line->unitPriceMinor()) }}</td>
                        <td class="num">{{ $line->quantity }}</td>
                        <td class="num">{{ \App\Support\Money::format($line->lineTotalMinor()) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3">Total</th>
                    <th class="num">{{ \App\Support\Money::format($totalMinor) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>

    <p class="muted">
        No payment provider, shipping, tax or stock handling is involved. Submitting
        this form records a completed synthetic order.
    </p>

    <form method="POST" action="{{ route('checkout.store') }}">
        @csrf
        <button type="submit">Place synthetic order</button>
    </form>
@endsection
