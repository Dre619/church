<?php

namespace App\Notifications;

use App\Models\OfflinePaymentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfflinePaymentSubmitted extends Notification
{
    use Queueable;

    public function __construct(public readonly OfflinePaymentRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount   = format_currency($this->request->amount, $this->request->organization?->currency ?? 'ZMW');
        $submitter = $this->request->user?->name ?? 'A member';
        $category  = $this->request->category?->name ?? 'Uncategorised';

        return (new MailMessage)
            ->subject("Offline Payment Awaiting Approval – {$amount}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$submitter} has submitted an offline payment that needs your review.")
            ->line("**Amount:** {$amount}")
            ->line("**Category:** {$category}")
            ->line("**Reference:** " . ($this->request->reference ?? 'N/A'))
            ->action('Review Payment', route('organization.offline-payment-review'))
            ->line('Please log in to approve or reject this payment.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
