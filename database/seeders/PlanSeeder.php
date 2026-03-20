<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Remove any existing test/placeholder plans
        Plan::whereIn('slug', ['test', 'test-plan', 'basic', 'starter', 'standard', 'premium', 'free-trial'])->delete();

        // Free 3-day trial — auto-assigned to new organizations
        Plan::create([
            'name'             => 'Free Trial',
            'slug'             => 'free-trial',
            'description'      => 'Try The Treasurer free for 3 days — no credit card required.',
            'price'            => 0.00,
            'max_members'      => 50,
            'is_active'        => true,
            'is_trial'         => true,
            'trial_days'       => 3,
            'can_view_reports' => false,
            'can_export'       => false,
        ]);

        // Starter — reports yes, exports no
        Plan::create([
            'name'             => 'Starter',
            'slug'             => 'starter',
            'description'      => 'Perfect for small congregations getting started.',
            'price'            => 99.00,
            'max_members'      => 100,
            'is_active'        => true,
            'is_trial'         => false,
            'trial_days'       => null,
            'can_view_reports' => true,
            'can_export'       => false,
        ]);

        // Standard — reports + exports
        Plan::create([
            'name'             => 'Standard',
            'slug'             => 'standard',
            'description'      => 'For growing churches that need more members and features.',
            'price'            => 249.00,
            'max_members'      => 500,
            'is_active'        => true,
            'is_trial'         => false,
            'trial_days'       => null,
            'can_view_reports' => true,
            'can_export'       => true,
        ]);

        // Premium — everything, unlimited members
        Plan::create([
            'name'             => 'Premium',
            'slug'             => 'premium',
            'description'      => 'Unlimited growth for large congregations and networks.',
            'price'            => 499.00,
            'max_members'      => null,
            'is_active'        => true,
            'is_trial'         => false,
            'trial_days'       => null,
            'can_view_reports' => true,
            'can_export'       => true,
        ]);
    }
}
