<?php

namespace App\Console\Commands;

use App\Models\OrganizationPlan;
use App\Models\User;
use App\Notifications\SubscriptionExpiringSoon;
use Illuminate\Console\Command;

class SendSubscriptionExpiryReminders extends Command
{
    protected $signature   = 'notifications:subscription-expiry';

    protected $description = 'Email org owners whose active subscription expires in 3 or 7 days';

    public function handle(): int
    {
        $sent = 0;

        OrganizationPlan::query()
            ->with(['organization.owner', 'plan'])
            ->where('is_active', true)
            ->whereIn(\Illuminate\Support\Facades\DB::raw('DATEDIFF(end_date, CURDATE())'), [3, 7])
            ->each(function (OrganizationPlan $orgPlan) use (&$sent) {
                $owner = $orgPlan->organization?->owner;

                if ($owner && $owner->email) {
                    $owner->notify(new SubscriptionExpiringSoon($orgPlan));
                    $sent++;
                }
            });

        $this->info("Sent {$sent} subscription expiry reminder(s).");

        return self::SUCCESS;
    }
}
