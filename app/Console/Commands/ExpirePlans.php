<?php

namespace App\Console\Commands;

use App\Models\OrganizationPlan;
use Illuminate\Console\Command;

class ExpirePlans extends Command
{
    protected $signature   = 'plans:expire';

    protected $description = 'Mark organization plans whose end_date has passed as inactive';

    public function handle(): int
    {
        $count = OrganizationPlan::query()
            ->where('is_active', true)
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->update(['is_active' => false]);

        $this->info("Expired {$count} plan(s).");

        return self::SUCCESS;
    }
}
