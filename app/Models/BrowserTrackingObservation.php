<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Passive browser evidence collected for a controlled experiment run.
 *
 * Rows deliberately are not deduplicated: repeated tracker requests are
 * evidence in their own right. Third-party query strings are never stored.
 */
class BrowserTrackingObservation extends Model
{
    protected $fillable = [
        'experiment_run_id', 'ground_truth_event_id', 'provider', 'layer',
        'resource_kind', 'canonical_event_name', 'provider_event_name',
        'outcome', 'correlation_method', 'request_method', 'request_host',
        'request_path', 'request_fingerprint', 'observed_at', 'finished_at',
        'duration_ms', 'response_status', 'failure_text', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'response_status' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function experimentRun(): BelongsTo
    {
        return $this->belongsTo(ExperimentRun::class);
    }

    public function groundTruthEvent(): BelongsTo
    {
        return $this->belongsTo(GroundTruthEvent::class);
    }
}
