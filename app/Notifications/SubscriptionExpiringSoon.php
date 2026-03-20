<?php

namespace App\Notifications;

use App\Models\OrganizationPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringSoon extends Notification
{
    use Queueable;

    public function __construct(public readonly OrganizationPlan $organizationPlan) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan    = $this->organizationPlan->plan?->name ?? 'your plan';
        $org     = $this->organizationPlan->organization?->name;
        $expiry  = $this->organizationPlan->end_date?->format('F j, Y');
        $daysLeft = (int) now()->diffInDays($this->organizationPlan->end_date);

        return (new MailMessage)
            ->subject("Your {$plan} subscription expires in {$daysLeft} day(s)")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your **{$plan}** subscription for **{$org}** will expire on **{$expiry}** ({$daysLeft} day(s) remaining).")
            ->line("Renew now to avoid any interruption to your financial records and reports.")
            ->action('Renew Subscription', route('subscription.plans'))
            ->line("If you have any questions, please contact support.");
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
