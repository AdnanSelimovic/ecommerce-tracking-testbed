<?php

namespace App\Models;

use App\Enums\ExperimentRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * A single controlled measurement run.
 *
 * Its controlled conditions are populated progressively by later milestones.
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
            'status' => ExperimentRunStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            $run->run_id ??= (string) Str::uuid();
            $run->status ??= ExperimentRunStatus::Pending;
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

    public function transitionTo(ExperimentRunStatus $next): self
    {
        $current = $this->status;

        if (! $current->canTransitionTo($next)) {
            throw new LogicException("Experiment run cannot transition from {$current->value} to {$next->value}.");
        }

        $attributes = ['status' => $next];

        if ($next === ExperimentRunStatus::Running) {
            $attributes['started_at'] = now();
        }

        if (in_array($next, [ExperimentRunStatus::Completed, ExperimentRunStatus::Failed, ExperimentRunStatus::Cancelled], true)) {
            $attributes['finished_at'] = now();
        }

        $this->fill($attributes)->save();

        return $this;
    }
}
