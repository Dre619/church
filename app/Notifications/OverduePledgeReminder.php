<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class OverduePledgeReminder extends Notification
{
    use Queueable;

    /** @param Collection<int, \App\Models\Pledge> $pledges */
    public function __construct(
        public readonly Collection $pledges,
        public readonly string $orgName,
        public readonly string $currency,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count   = $this->pledges->count();
        $total   = format_currency($this->pledges->sum('amount'), $this->currency);
        $message = (new MailMessage)
            ->subject("Overdue Pledge Reminder – {$this->orgName}")
            ->greeting("Dear {$notifiable->name},")
            ->line("This is a friendly reminder that you have **{$count} overdue pledge(s)** totalling **{$total}** with {$this->orgName}.");

        foreach ($this->pledges as $pledge) {
            $balance  = format_currency(max(0, $pledge->amount - ($pledge->fulfilled_amount ?? 0)), $this->currency);
            $deadline = $pledge->deadline?->format('F j, Y');
            $message->line("• **{$pledge->project?->name ?? 'General Pledge'}** — Balance: {$balance} (was due {$deadline})");
        }

        return $message
            ->action('View My Pledges', route('organization.pledges'))
            ->line("Please contact {$this->orgName} to discuss your pledge fulfillment.");
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
