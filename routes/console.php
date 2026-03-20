<?php

use App\Console\Commands\ExpirePlans;
use App\Console\Commands\SendOverduePledgeReminders;
use App\Console\Commands\SendSubscriptionExpiryReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ExpirePlans::class)->daily();
Schedule::command(SendSubscriptionExpiryReminders::class)->dailyAt('08:00');
Schedule::command(SendOverduePledgeReminders::class)->weeklyOn(1, '08:00'); // every Monday
