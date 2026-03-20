<?php

namespace App\Notifications;

use App\Models\OfflinePaymentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfflinePaymentStatusUpdated extends Notification
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
        $org      = $this->request->organization?->name;
        $approved = $this->request->isApproved();

        $message = (new MailMessage)
            ->subject($approved ? "Payment Approved – {$amount}" : "Payment Not Approved – {$amount}")
            ->greeting("Dear {$notifiable->name},");

        if ($approved) {
            $message
                ->line("Great news! Your offline payment of **{$amount}** has been **approved** by {$org}.")
                ->line("Your contribution has been recorded in our system.")
                ->action('View Receipt', route('payment.receipt', $this->request->payment_id));
        } else {
            $message
                ->line("Unfortunately, your offline payment of **{$amount}** could not be approved.")
                ->line("**Reason:** " . ($this->request->rejection_reason ?? 'No reason provided.'))
                ->line("Please contact {$org} if you have any questions.");
        }

        return $message->salutation("Regards,\n{$org}");
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
