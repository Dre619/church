<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Expense;

class ExpenseObserver
{
    public function created(Expense $expense): void
    {
        AuditLog::record(
            'created',
            $expense,
            "Expense '{$expense->title}' of {$expense->amount} recorded",
            [],
            $expense->only(['title', 'amount', 'category_id', 'expense_date']),
        );
    }

    public function updated(Expense $expense): void
    {
        $dirty = $expense->getDirty();

        if (array_keys($dirty) === ['reconciled', 'reconciled_at', 'reconciled_by']) {
            $label = $expense->reconciled ? 'reconciled' : 'unreconciled';
            AuditLog::record('updated', $expense, "Expense #{$expense->id} marked as {$label}");

            return;
        }

        if (empty($dirty)) {
            return;
        }

        AuditLog::record(
            'updated',
            $expense,
            "Expense '{$expense->title}' updated",
            array_intersect_key($expense->getOriginal(), array_flip(array_keys($dirty))),
            $dirty,
        );
    }

    public function deleted(Expense $expense): void
    {
        AuditLog::record(
            'deleted',
            $expense,
            "Expense '{$expense->title}' of {$expense->amount} deleted",
        );
    }
}
