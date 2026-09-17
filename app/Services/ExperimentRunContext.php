<?php

namespace App\Services;

use App\Enums\ExperimentRunStatus;
use App\Models\ExperimentRun;
use Illuminate\Contracts\Session\Session;
use LogicException;

/**
 * Holds the one active experimental run for the current browser session.
 */
class ExperimentRunContext
{
    private const SESSION_KEY = 'research.active_experiment_run_id';

    public function __construct(private readonly Session $session)
    {
    }

    public function bind(ExperimentRun $run): void
    {
        if ($run->status !== ExperimentRunStatus::Running) {
            throw new LogicException('Only a running experiment run can be bound to a browser session.');
        }

        $this->session->put(self::SESSION_KEY, $run->getKey());
    }

    public function current(): ?ExperimentRun
    {
        $id = $this->currentId();

        if ($id === null) {
            return null;
        }

        $run = ExperimentRun::find($id);

        if ($run === null || $run->status !== ExperimentRunStatus::Running) {
            $this->clear();

            return null;
        }

        return $run;
    }

    public function currentId(): ?int
    {
        $id = $this->session->get(self::SESSION_KEY);

        return is_int($id) || ctype_digit((string) $id) ? (int) $id : null;
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    public function clearIfCurrent(ExperimentRun $run): void
    {
        if ($this->currentId() === $run->getKey()) {
            $this->clear();
        }
    }
}
