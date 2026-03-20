<?php

namespace App\Notifications;

use App\Models\SubscriptionPaymentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPaymentStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(public readonly SubscriptionPaymentRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan   = $this->request->plan;
        $amount = format_currency($this->request->amount, 'ZMW');

        if ($this->request->status === 'approved') {
            return (new MailMessage)
                ->subject('Subscription Payment Approved – Your Plan is Active!')
                ->greeting("Hello {$notifiable->name},")
                ->line('Great news! Your offline subscription payment has been approved.')
                ->line("**Plan:** {$plan->name}")
                ->line("**Duration:** {$this->request->months} month(s)")
                ->line("**Amount:** {$amount}")
                ->action('Go to Dashboard', route('dashboard'))
                ->line('Your subscription is now active. Thank you!');
        }

        return (new MailMessage)
            ->subject('Subscription Payment Rejected')
            ->greeting("Hello {$notifiable->name},")
            ->line('Unfortunately your offline subscription payment has been rejected.')
            ->line("**Plan:** {$plan->name}")
            ->line("**Amount:** {$amount}")
            ->line("**Reason:** " . ($this->request->rejection_reason ?? 'No reason provided'))
            ->action('View Plans', route('subscription.plans'))
            ->line('Please resubmit with correct proof or contact support if you believe this is an error.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
