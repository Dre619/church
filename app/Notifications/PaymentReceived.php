<?php

namespace App\Notifications;

use App\Models\Payments;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceived extends Notification
{
    use Queueable;

    public function __construct(public readonly Payments $payment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $org      = $this->payment->organization;
        $amount   = format_currency($this->payment->amount, $org?->currency ?? 'ZMW');
        $category = $this->payment->category?->name ?? 'Contribution';
        $date     = $this->payment->donation_date?->format('F j, Y');

        return (new MailMessage)
            ->subject("Payment Received – {$amount} | {$org?->name}")
            ->greeting("Dear {$notifiable->name},")
            ->line("Thank you! Your payment has been recorded.")
            ->line("**Amount:** {$amount}")
            ->line("**Category:** {$category}")
            ->line("**Date:** {$date}")
            ->line("**Reference:** #{$this->payment->id}")
            ->action('View Receipt', route('payment.receipt', $this->payment->id))
            ->salutation("Blessings,\n{$org?->name}");
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
