<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'total_minor',
        'currency',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_minor' => 'integer',
            'status' => OrderStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function groundTruthEvents(): HasMany
    {
        return $this->hasMany(GroundTruthEvent::class);
    }
}
