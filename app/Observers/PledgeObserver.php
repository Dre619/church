<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Pledge;

class PledgeObserver
{
    public function created(Pledge $pledge): void
    {
        AuditLog::record(
            'created',
            $pledge,
            "Pledge of {$pledge->amount} created",
            [],
            $pledge->only(['amount', 'user_id', 'project_id', 'pledge_date', 'deadline', 'status']),
        );
    }

    public function updated(Pledge $pledge): void
    {
        $dirty = $pledge->getDirty();

        if (empty($dirty)) {
            return;
        }

        AuditLog::record(
            'updated',
            $pledge,
            "Pledge #{$pledge->id} updated",
            array_intersect_key($pledge->getOriginal(), array_flip(array_keys($dirty))),
            $dirty,
        );
    }

    public function deleted(Pledge $pledge): void
    {
        AuditLog::record(
            'deleted',
            $pledge,
            "Pledge #{$pledge->id} of {$pledge->amount} deleted",
        );
    }
}
