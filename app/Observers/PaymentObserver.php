<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Payments;
use App\Notifications\PaymentReceived;

class PaymentObserver
{
    public function created(Payments $payment): void
    {
        AuditLog::record(
            'created',
            $payment,
            "Payment of {$payment->amount} recorded for {$payment->name}",
            [],
            $payment->only(['name', 'amount', 'category_id', 'payment_method', 'donation_date']),
        );

        // Send receipt email to member if they have an account with an email
        $user = $payment->user;

        if ($user && $user->email) {
            $user->notify(new PaymentReceived($payment));
        }
    }

    public function updated(Payments $payment): void
    {
        $dirty = $payment->getDirty();

        if (array_keys($dirty) === ['reconciled', 'reconciled_at', 'reconciled_by']) {
            $label = $payment->reconciled ? 'reconciled' : 'unreconciled';
            AuditLog::record('updated', $payment, "Payment #{$payment->id} marked as {$label}");

            return;
        }

        if (empty($dirty)) {
            return;
        }

        AuditLog::record(
            'updated',
            $payment,
            "Payment #{$payment->id} updated",
            array_intersect_key($payment->getOriginal(), array_flip(array_keys($dirty))),
            $dirty,
        );
    }

    public function deleted(Payments $payment): void
    {
        AuditLog::record(
            'deleted',
            $payment,
            "Payment of {$payment->amount} for {$payment->name} deleted",
        );
    }
}
