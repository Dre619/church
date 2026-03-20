<?php

use App\Http\Controllers\GivingStatementController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\ProcessPayment;
use App\Http\Controllers\SwitchBranchController;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    //->where('is_trial', false)
    $plans = Plan::where('is_active', true)->orderBy('price')->get();

    return view('welcome', compact('plans'));
})->name('home');

Route::livewire('dashboard', 'pages::dashboard')
    ->middleware(['auth', 'verified', 'activeSubscription'])
    ->name('dashboard');

Route::middleware('auth')->group(function(){
    Route::livewire('admin/users','pages::admin.users')->name('admin.users');
    Route::livewire('admin/organization-payments','pages::admin.organization-payments')->name('admin.organization-payments');
    Route::livewire('admin/organizations','pages::admin.organizations')->name('admin.organizations');
    Route::livewire('admin/organization-plans','pages::admin.organization-plans')->name('admin.organization-plans');
    Route::livewire('admin/plans','pages::admin.plans')->name('admin.plans');
    Route::livewire('admin/subscription-payment-review','pages::admin.subscription-payment-review')->name('admin.subscription-payment-review');
});

Route::middleware('auth')->group(function(){
    Route::livewire('user/organization','pages::user.organization.church')->name('create.organization');
    Route::post('confirm/payment',[ProcessPayment::class,'confirmPayment'])->name('subscription.payment.callback');
    Route::get('switch-branch/{orgId}', SwitchBranchController::class)->name('branch.switch');
    Route::post('onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::post('onboarding/reset', [OnboardingController::class, 'reset'])->name('onboarding.reset');
});

Route::middleware(['auth', 'activeSubscription'])->group(function () {
    Route::livewire('user/organization/branches', 'pages::user.organization.branches')->name('organization.branches');
});

Route::middleware(['auth','activeSubscription'])->group(function(){
    Route::livewire('user/organization/members','pages::user.organization.members')->name('organization.members');
    Route::livewire('user/organization/payment/categories','pages::user.organization.payment-categories')->name('organization.payment.categories');
    Route::livewire('user/organization/payments','pages::user.organization.payments')->name('organization.payments');
    Route::livewire('user/organization/pledges','pages::user.organization.pledges')->name('organization.pledges');
    Route::livewire('user/organization/projects','pages::user.organization.projects')->name('organization.projects');
    Route::livewire('user/organization/expenses','pages::user.organization.expenses')->name('organization.expenses');
    Route::livewire('user/organization/expense/category','pages::user.organization.expense-categories')->name('organization.expense.categories');
    //reports
    Route::livewire('user/organization/reports/collections','pages::user.organization.reports.collections')->name('reports.collections');
    Route::livewire('user/organization/reports/expenses','pages::user.organization.reports.expenses')->name('reports.expenses');
    Route::livewire('user/organization/reports/pledges','pages::user.organization.reports.pledges')->name('reports.pledges');
    // treasurer tools
    Route::livewire('user/organization/budgets','pages::user.organization.budgets')->name('organization.budgets');
    Route::livewire('user/organization/audit-log','pages::user.organization.audit-log')->name('organization.audit-log');
    Route::livewire('user/organization/giving-statement','pages::user.organization.giving-statement')->name('organization.giving-statement');
    Route::get('user/organization/giving-statement/{userId}/{year}/pdf', [GivingStatementController::class, 'download'])
        ->name('giving.statement.download');
    // offline payments
    Route::livewire('user/organization/offline-payments','pages::user.organization.offline-payments')->name('organization.offline-payments');
    Route::livewire('user/organization/offline-payment-review','pages::user.organization.offline-payment-review')->name('organization.offline-payment-review');
});

Route::middleware(['auth', 'hasOrganization'])->group(function () {
    Route::get('payments/{payment}/receipt', [PaymentReceiptController::class, 'show'])->name('payment.receipt');
});

Route::middleware(['auth','hasOrganization'])->group(function(){
    Route::livewire('user/plans','pages::user.subscription.plans')->name('subscription.plans');
    Route::livewire('user/expired-plans','pages::user.subscription.expired')->name('expired.plans');
});

require __DIR__.'/settings.php';
