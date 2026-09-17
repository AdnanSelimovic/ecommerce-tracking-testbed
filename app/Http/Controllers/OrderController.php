<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;

class OrderController extends Controller
{
    /**
     * Order confirmation. This action is intentionally read-only: refreshing it
     * must never create another order or another purchase ground-truth event.
     */
    public function show(Order $order): View
    {
        return view('orders.show', [
            'order' => $order->load('items'),
        ]);
    }
}
