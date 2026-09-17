<?php

namespace App\Tracking;

use App\Models\ExperimentRun;
use App\Models\GroundTruthEvent;
use App\Services\ExperimentRunContext;

class ClientTrackingEligibility
{
    public function __construct(private readonly ExperimentRunContext $context)
    {
    }

    public function allowsRun(?ExperimentRun $run): bool
    {
        return $run !== null
            && in_array($run->tracking_mode, ['client_only', 'server_augmented'], true)
            && $run->consent_mode === 'full';
    }

    public function allows(GroundTruthEvent $event): bool
    {
        $currentRun = $this->context->current();

        return $this->allowsRun($currentRun)
            && $event->experiment_run_id === $currentRun?->id;
    }
}
