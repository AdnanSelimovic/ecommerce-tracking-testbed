<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A single controlled measurement run.
 *
 * The runner itself is not implemented yet; this model only establishes the
 * schema that later milestones (tracking modes, blocking, consent, Playwright
 * automation) will populate.
 */
class ExperimentRun extends Model
{
    protected $fillable = [
        'run_id',
        'tracking_mode',
        'blocking_mode',
        'privacy_mode',
        'consent_mode',
        'browser',
        'browser_version',
        'status',
        'started_at',
        'finished_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            $run->run_id ??= (string) Str::uuid();
            $run->status ??= 'pending';
        });
    }

    public function getRouteKeyName(): string
    {
        return 'run_id';
    }

    public function groundTruthEvents(): HasMany
    {
        return $this->hasMany(GroundTruthEvent::class);
    }
}
