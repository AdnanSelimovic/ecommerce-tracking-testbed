<?php

namespace App\Enums;

enum ExperimentRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Running, self::Cancelled], true),
            self::Running => in_array($next, [self::Completed, self::Failed, self::Cancelled], true),
            self::Completed, self::Failed, self::Cancelled => false,
        };
    }
}
