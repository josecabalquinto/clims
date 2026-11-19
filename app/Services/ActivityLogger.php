<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /** @param array<string, mixed> $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(?User $actor, string $action, Model $subject, array $oldValues = [], array $newValues = []): ActivityLog
    {
        // Explicit allowlists also exclude future authentication secrets by default.
        $allowed = ActivityLog::allowedFields($subject->getMorphClass());
        if ($allowed === []) {
            throw new \InvalidArgumentException('Unsupported activity subject.');
        }

        $log = new ActivityLog;
        $log->user_id = $actor?->id;
        $log->action = $action;
        $log->subject_type = $subject->getMorphClass();
        $log->subject_id = (int) $subject->getKey();
        $log->old_values = array_intersect_key($oldValues, array_flip($allowed));
        $log->new_values = array_intersect_key($newValues, array_flip($allowed));
        $log->save();

        return $log;
    }
}
