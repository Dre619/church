<?php

use App\Models\Expense;
use App\Models\Organization;
use App\Models\OrganizationPayment;
use App\Models\OrganizationPlan;
use App\Models\OrganizationUser;
use App\Models\Payments;
use App\Models\Pledge;
use App\Models\User;
use Livewire\Component;

new class extends Component
{
    public int $totalUsers           = 0;
    public int $totalOrganizations   = 0;
    public int $activeSubscriptions  = 0;
    public float $totalRevenue       = 0;

    public int $totalMembers         = 0;
    public float $collectionsMonth   = 0;
    public float $expensesMonth      = 0;
    public int $pendingPledges       = 0;

    /** @var array<int, array<string, mixed>> */
    public array $recentUsers         = [];

    /** @var array<int, array<string, mixed>> */
    public array $recentOrganizations = [];

    /** @var array<int, array<string, mixed>> */
    public array $recentPayments      = [];

    public string $organizationName   = '';
    public string $currency           = 'ZMW';

    /** @var array<int, array<string, mixed>> */
    public array $monthlyChart        = [];

    /** @var array<int, array<string, mixed>> */
    public array $overduePledgesList  = [];

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->role === 'admin') {
            $this->loadAdminData();
        } else {
            $this->loadUserData();
        }
    }

    protected function loadAdminData(): void
    {
        $this->totalUsers          = User::query()->count();
        $this->totalOrganizations  = Organization::query()->count();
        $this->activeSubscriptions = OrganizationPlan::query()->active()->count();
        $this->totalRevenue        = (float) OrganizationPayment::query()->where('status', 'success')->sum('amount');

        $this->recentUsers = User::query()
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'role'       => $u->role,
                'created_at' => $u->created_at?->format('M d, Y'),
            ])
            ->toArray();

        $this->recentOrganizations = Organization::query()
            ->with(['owner', 'activePlan.plan'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($org) => [
                'id'          => $org->id,
                'name'        => $org->name,
                'owner'       => $org->owner?->name,
                'plan'        => $org->activePlan?->plan?->name ?? 'No Plan',
                'created_at'  => $org->created_at?->format('M d, Y'),
            ])
            ->toArray();
    }

    protected function loadUserData(): void
    {
        $orgUser = auth()->user()->myOrganization;

        if (! $orgUser) {
            return;
        }

        $organizationId = $orgUser->organization_id;

        $organization = Organization::query()->find($organizationId);
        $this->organizationName = $organization?->name ?? '';
        $this->currency         = $organization?->currency ?? 'ZMW';

        $this->totalMembers = OrganizationUser::query()
            ->where('organization_id', $organizationId)
            ->count();

        $this->collectionsMonth = (float) Payments::query()
            ->where('organization_id', $organizationId)
            ->whereMonth('donation_date', now()->month)
            ->whereYear('donation_date', now()->year)
            ->sum('amount');

        $this->expensesMonth = (float) Expense::query()
            ->where('organization_id', $organizationId)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount');

        $this->pendingPledges = Pledge::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'pending')
            ->count();

        $this->recentPayments = Payments::query()
            ->where('organization_id', $organizationId)
            ->with(['user', 'category'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($p) => [
                'id'            => $p->id,
                'name'          => $p->user->name,
                'amount'        => $p->amount,
                'category'      => $p->category?->name ?? 'N/A',
                'payment_method'=> $p->payment_method,
                'donation_date' => $p->donation_date,
            ])
            ->toArray();

        $year = now()->year;

        $incomeByMonth = Payments::query()
            ->where('organization_id', $organizationId)
            ->whereYear('donation_date', $year)
            ->selectRaw('MONTH(donation_date) as month, SUM(amount) as total')
            ->groupBy('month')
            ->pluck('total', 'month');

        $expenseByMonth = Expense::query()
            ->where('organization_id', $organizationId)
            ->whereYear('expense_date', $year)
            ->selectRaw('MONTH(expense_date) as month, SUM(amount) as total')
            ->groupBy('month')
            ->pluck('total', 'month');

        $this->monthlyChart = collect(range(1, 12))->map(fn ($m) => [
            'month'   => date('M', mktime(0, 0, 0, $m, 1)),
            'income'  => (float) ($incomeByMonth[$m] ?? 0),
            'expense' => (float) ($expenseByMonth[$m] ?? 0),
        ])->toArray();

        $this->overduePledgesList = Pledge::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'pending')
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->with('user')
            ->orderBy('deadline')
            ->take(5)
            ->get()
            ->map(fn ($pl) => [
                'id'       => $pl->id,
                'name'     => $pl->user?->name ?? '—',
                'amount'   => (float) $pl->amount,
                'paid'     => (float) ($pl->fulfilled_amount ?? 0),
                'deadline' => $pl->deadline?->format('M d, Y'),
            ])
            ->toArray();
    }
};
?>

<div class="py-6">
    <div class="mx-auto px-4 sm:px-6 md:px-8">

        {{-- ── Admin Dashboard ─────────────────────────────────────────────── --}}
        @if(auth()->user()->role === 'admin')

            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Admin Dashboard</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Platform overview and recent activity</p>
            </div>

            {{-- Stat Cards --}}
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">

                <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Users</dt>
                                    <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalUsers) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-zinc-700 px-5 py-3">
                        <a href="{{ route('admin.users') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">View all users</a>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Organizations</dt>
                                    <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalOrganizations) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-zinc-700 px-5 py-3">
                        <a href="{{ route('admin.organizations') }}" wire:navigate class="text-sm font-medium text-green-600 hover:text-green-500 dark:text-green-400">View all organizations</a>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Active Subscriptions</dt>
                                    <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($activeSubscriptions) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-zinc-700 px-5 py-3">
                        <a href="{{ route('admin.organization-plans') }}" wire:navigate class="text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">View subscriptions</a>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Revenue</dt>
                                    <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ format_currency($totalRevenue, 'ZMW') }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-zinc-700 px-5 py-3">
                        <a href="{{ route('admin.organization-payments') }}" wire:navigate class="text-sm font-medium text-yellow-600 hover:text-yellow-500 dark:text-yellow-400">View payments</a>
                    </div>
                </div>

            </div>

            {{-- Recent Data Tables --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                {{-- Recent Organizations --}}
                <div class="bg-white dark:bg-zinc-800 shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Organizations</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                            <thead class="bg-gray-50 dark:bg-zinc-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Owner</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Plan</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Joined</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                                @forelse($recentOrganizations as $org)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ $org['name'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $org['owner'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $org['plan'] === 'No Plan' ? 'bg-gray-100 text-gray-800 dark:bg-zinc-600 dark:text-gray-300' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' }}">
                                                {{ $org['plan'] }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $org['created_at'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-400">No organizations yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Recent Users --}}
                <div class="bg-white dark:bg-zinc-800 shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Users</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                            <thead class="bg-gray-50 dark:bg-zinc-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Joined</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                                @forelse($recentUsers as $user)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-8 w-8 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center">
                                                    <span class="text-indigo-600 dark:text-indigo-300 text-xs font-semibold">{{ strtoupper(substr($user['name'], 0, 1)) }}</span>
                                                </div>
                                                <div class="ml-3 text-sm font-medium text-gray-900 dark:text-white">{{ $user['name'] }}</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $user['email'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $user['role'] === 'admin' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' }}">
                                                {{ ucfirst($user['role']) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $user['created_at'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-400">No users yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        {{-- ── User Dashboard ───────────────────────────────────────────────── --}}
        @else

            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $organizationName ?: 'Dashboard' }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ now()->format('F Y') }} overview</p>
            </div>

            {{-- Stat Cards --}}
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">

                <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Members</dt>
                                    <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalMembers) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-zinc-700 px-5 py-3">
                        <a href="{{ route('organization.members') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">View members</a>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Collections This Month</dt>
                                    <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ format_currency($collectionsMonth, $currency) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-zinc-700 px-5 py-3">
                        <a href="{{ route('organization.payments') }}" wire:navigate class="text-sm font-medium text-green-600 hover:text-green-500 dark:text-green-400">View collections</a>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Expenses This Month</dt>
                                    <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ format_currency($expensesMonth, $currency) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-zinc-700 px-5 py-3">
                        <a href="{{ route('organization.expenses') }}" wire:navigate class="text-sm font-medium text-red-600 hover:text-red-500 dark:text-red-400">View expenses</a>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Pending Pledges</dt>
                                    <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($pendingPledges) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-zinc-700 px-5 py-3">
                        <a href="{{ route('organization.pledges') }}" wire:navigate class="text-sm font-medium text-yellow-600 hover:text-yellow-500 dark:text-yellow-400">View pledges</a>
                    </div>
                </div>

            </div>

            {{-- Monthly Chart --}}
            <div class="mb-8 bg-white dark:bg-zinc-800 shadow rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Income vs Expenses — {{ now()->year }}</h3>
                <canvas id="monthlyChart" height="90"></canvas>
            </div>

            {{-- Overdue Pledges --}}
            @if(count($overduePledgesList) > 0)
            <div class="mb-8 bg-white dark:bg-zinc-800 shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-2 h-2 rounded-full bg-red-500"></span>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Overdue Pledges</h3>
                    </div>
                    <a href="{{ route('organization.pledges') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">View all</a>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-zinc-700">
                    @foreach($overduePledgesList as $pl)
                        <div class="px-6 py-3 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $pl['name'] }}</p>
                                <p class="text-xs text-red-500">Due {{ $pl['deadline'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ format_currency($pl['amount'], $currency) }}</p>
                                <p class="text-xs text-gray-400">Paid: {{ format_currency($pl['paid'], $currency) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Recent Collections --}}
            <div class="bg-white dark:bg-zinc-800 shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-zinc-700 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Collections</h3>
                    <a href="{{ route('organization.payments') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">View all</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                        <thead class="bg-gray-50 dark:bg-zinc-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Member</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Method</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                            @forelse($recentPayments as $payment)
                                <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ $payment['name'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $payment['category'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600 dark:text-green-400">{{ format_currency($payment['amount'], $currency) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ ucwords(str_replace('_',' ',$payment['payment_method'])) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $payment['donation_date'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400">No collections recorded yet</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        @endif

    </div>
</div>

@if(auth()->user()->role !== 'admin')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const data = @json($monthlyChart);
    if (!data || !data.length) return;

    const ctx = document.getElementById('monthlyChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(d => d.month),
            datasets: [
                {
                    label: 'Income',
                    data: data.map(d => d.income),
                    backgroundColor: 'rgba(34,197,94,0.7)',
                    borderRadius: 4,
                },
                {
                    label: 'Expenses',
                    data: data.map(d => d.expense),
                    backgroundColor: 'rgba(239,68,68,0.7)',
                    borderRadius: 4,
                },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } },
        },
    });
});
</script>
@endif
