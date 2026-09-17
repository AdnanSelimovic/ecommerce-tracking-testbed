<?php

namespace App\Models;

use App\Enums\GroundTruthEventName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Backend-recorded ecommerce event: the source of truth for this research.
 *
 * Intentionally vendor-neutral. Do not add GA4- or Meta-specific fields here;
 * measurement-system payloads belong in their own tables in later milestones.
 */
class GroundTruthEvent extends Model
{
    protected $fillable = [
        'event_id',
        'experiment_run_id',
        'event_name',
        'product_id',
        'order_id',
        'quantity',
        'value_minor',
        'currency',
        'occurred_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'event_name' => GroundTruthEventName::class,
            'quantity' => 'integer',
            'value_minor' => 'integer',
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->event_id ??= (string) Str::uuid();
            $event->occurred_at ??= now();
        });
    }

    public function experimentRun(): BelongsTo
    {
        return $this->belongsTo(ExperimentRun::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function browserTrackingObservations(): HasMany
    {
        return $this->hasMany(BrowserTrackingObservation::class);
    }
}
