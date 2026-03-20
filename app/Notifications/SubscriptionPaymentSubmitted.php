<?php

namespace App\Notifications;

use App\Models\SubscriptionPaymentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPaymentSubmitted extends Notification
{
    use Queueable;

    public function __construct(public readonly SubscriptionPaymentRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $org    = $this->request->organization;
        $plan   = $this->request->plan;
        $amount = format_currency($this->request->amount, 'ZMW');

        return (new MailMessage)
            ->subject("Subscription Payment Awaiting Approval – {$org->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$org->name} has submitted an offline subscription payment that needs your review.")
            ->line("**Plan:** {$plan->name}")
            ->line("**Duration:** {$this->request->months} month(s)")
            ->line("**Amount:** {$amount}")
            ->line("**Reference:** " . ($this->request->reference ?? 'N/A'))
            ->action('Review Payment', route('admin.subscription-payment-review'))
            ->line('Please log in to approve or reject this subscription payment.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
