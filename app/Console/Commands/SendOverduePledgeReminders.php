<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Pledge;
use App\Notifications\OverduePledgeReminder;
use Illuminate\Console\Command;

class SendOverduePledgeReminders extends Command
{
    protected $signature   = 'notifications:overdue-pledges';

    protected $description = 'Email members who have overdue unfulfilled pledges';

    public function handle(): int
    {
        $sent = 0;

        // Group overdue pledges by user within each organisation
        Pledge::query()
            ->with(['user', 'organization'])
            ->where('status', 'pending')
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->get()
            ->groupBy('user_id')
            ->each(function ($pledges, $userId) use (&$sent) {
                $user = $pledges->first()->user;

                if (! $user || ! $user->email) {
                    return;
                }

                // Group by org so the email is scoped per organisation
                $pledges->groupBy('organization_id')->each(function ($orgPledges) use ($user, &$sent) {
                    $org      = $orgPledges->first()->organization;
                    $currency = $org?->currency ?? 'ZMW';

                    $user->notify(new OverduePledgeReminder($orgPledges, $org?->name ?? 'the church', $currency));
                    $sent++;
                });
            });

        $this->info("Sent {$sent} overdue pledge reminder(s).");

        return self::SUCCESS;
    }
}
