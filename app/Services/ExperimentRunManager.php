<?php

namespace App\Services;

use App\Enums\ExperimentRunStatus;
use App\Models\ExperimentRun;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * Creates and transitions controlled research runs.
 */
class ExperimentRunManager
{
    public function __construct(private readonly ExperimentRunContext $context)
    {
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes = []): ExperimentRun
    {
        $validated = Validator::make($attributes, [
            'tracking_mode' => ['nullable', 'in:client_only,server_augmented'],
            'blocking_mode' => ['nullable', 'in:none,controlled,real_blocker'],
            'privacy_mode' => ['nullable', 'in:standard,restrictive'],
            'consent_mode' => ['nullable', 'in:full,partial,none'],
            'browser' => ['nullable', 'string', 'max:255'],
            'browser_version' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        return ExperimentRun::create(Arr::only($validated, [
            'tracking_mode', 'blocking_mode', 'privacy_mode', 'consent_mode',
            'browser', 'browser_version', 'metadata',
        ]));
    }

    public function start(ExperimentRun $run): ExperimentRun
    {
        return $run->transitionTo(ExperimentRunStatus::Running);
    }

    public function complete(ExperimentRun $run): ExperimentRun
    {
        $run = $run->transitionTo(ExperimentRunStatus::Completed);
        $this->context->clearIfCurrent($run);

        return $run;
    }
}
