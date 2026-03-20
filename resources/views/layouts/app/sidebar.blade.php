<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        @php
            $role = auth()->user()->role;
            $branchRole = \App\Models\OrganizationUser::query()
                ->where('user_id', auth()->id())
                ->when(
                    session('current_org_id'),
                    fn ($q) => $q->where('organization_id', session('current_org_id')),
                    fn ($q) => $q->orderByRaw("FIELD(branch_role, 'owner', 'manager', 'member')")
                )
                ->value('branch_role') ?? 'member';
        @endphp
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">

        {{-- ── Global loading bar ───────────────────────────────────────────── --}}
        <div id="global-loader" aria-hidden="true"
             style="position:fixed;top:0;left:0;right:0;z-index:9999;pointer-events:none;">
            <div id="global-loader-bar"
                 style="height:3px;width:0%;background:linear-gradient(90deg,#6366f1,#8b5cf6,#a78bfa);
                        transition:width .2s ease,opacity .3s ease;opacity:0;
                        box-shadow:0 0 8px rgba(99,102,241,.6);">
            </div>
        </div>

        {{-- ── Global loading overlay (shown after 600 ms of waiting) ─────── --}}
        <div id="global-overlay"
             role="status" aria-live="polite" aria-label="Loading"
             style="display:none;position:fixed;inset:0;z-index:10000;
                    background:rgba(0,0,0,.45);backdrop-filter:blur(2px);
                    align-items:center;justify-content:center;flex-direction:column;gap:16px;">
            <div style="background:#fff;border-radius:16px;padding:32px 40px;
                        box-shadow:0 20px 60px rgba(0,0,0,.25);
                        display:flex;flex-direction:column;align-items:center;gap:16px;min-width:200px;">
                {{-- Spinner --}}
                <svg style="width:44px;height:44px;animation:spin .8s linear infinite;color:#6366f1;"
                     viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                            stroke-dasharray="31.416" stroke-dashoffset="10" opacity=".25"/>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
                <div style="text-align:center;">
                    <p style="font-size:.9rem;font-weight:600;color:#111827;margin:0 0 4px;">Please wait…</p>
                    <p style="font-size:.75rem;color:#6b7280;margin:0;">The system is processing your request</p>
                </div>
            </div>
        </div>

        <style>
            @keyframes spin { to { transform: rotate(360deg); } }
        </style>

        <script>
            (function () {
                var bar     = document.getElementById('global-loader-bar');
                var overlay = document.getElementById('global-overlay');
                var active  = 0;
                var barTimer, overlayTimer;

                function start() {
                    active++;
                    // Progress bar — immediate
                    clearTimeout(barTimer);
                    bar.style.transition = 'width .2s ease, opacity .15s ease';
                    bar.style.opacity    = '1';
                    bar.style.width      = '40%';
                    barTimer = setTimeout(function () { bar.style.width = '70%'; }, 400);

                    // Overlay — only if still loading after 600 ms
                    clearTimeout(overlayTimer);
                    overlayTimer = setTimeout(function () {
                        if (active > 0) {
                            overlay.style.display = 'flex';
                        }
                    }, 600);
                }

                function done() {
                    active = Math.max(0, active - 1);
                    if (active > 0) { return; }

                    // Hide overlay
                    clearTimeout(overlayTimer);
                    overlay.style.display = 'none';

                    // Finish progress bar
                    clearTimeout(barTimer);
                    bar.style.transition = 'width .15s ease, opacity .4s ease .15s';
                    bar.style.width      = '100%';
                    barTimer = setTimeout(function () {
                        bar.style.opacity = '0';
                        barTimer = setTimeout(function () { bar.style.width = '0%'; }, 400);
                    }, 150);
                }

                // Livewire component requests
                document.addEventListener('livewire:request',  start);
                document.addEventListener('livewire:response', done);

                // wire:navigate page transitions
                document.addEventListener('livewire:navigating', start);
                document.addEventListener('livewire:navigated',  done);
            })();
        </script>

        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item id="tour-dashboard" icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @if(in_array($role,['admin']))
                        <flux:sidebar.item icon="Users" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>
                            {{ __('Users') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-text" :href="route('admin.organizations')" :current="request()->routeIs('admin.organizations')" wire:navigate>
                            {{ __('Organizations') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="credit-card" :href="route('admin.organization-payments')" :current="request()->routeIs('admin.organization-payments')" wire:navigate>
                            {{ __('Payments') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-text" :href="route('admin.organization-plans')" :current="request()->routeIs('admin.organization-plans')" wire:navigate>
                            {{ __('Organization Plans') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-text" :href="route('admin.plans')" :current="request()->routeIs('admin.plans')" wire:navigate>
                            {{ __('Plans') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="banknotes" :href="route('admin.subscription-payment-review')" :current="request()->routeIs('admin.subscription-payment-review')" wire:navigate>
                            {{ __('Subscription Payments') }}
                            @php $pendingSubPayments = \App\Models\SubscriptionPaymentRequest::pending()->count(); @endphp
                            @if($pendingSubPayments > 0)
                                <flux:badge size="sm" color="yellow" class="ml-auto">{{ $pendingSubPayments }}</flux:badge>
                            @endif
                        </flux:sidebar.item>
                    @endif
                    @if(in_array($role, ['user'])||in_array($branchRole, ['manager']))

    {{-- ── Branch Switcher ──────────────────────────────────────────────────── --}}
    @php
        $currentOrgUser = auth()->user()->myOrganization;
        $currentOrg     = $currentOrgUser?->organization;
        $allOrgUsers    = auth()->user()->myOrganizations()->with('organization')->get();
    @endphp
    @if($currentOrg && $allOrgUsers->count() > 1 && in_array($branchRole, ['owner']))
    <div class="mx-3 mb-2">
        <flux:dropdown>
            <flux:button size="sm" variant="ghost" icon-trailing="chevron-down"
                         class="w-full justify-between truncate text-left text-xs">
                <span class="truncate font-semibold text-gray-700 dark:text-gray-200">{{ $currentOrg->name }}</span>
            </flux:button>
            <flux:menu>
                @foreach($allOrgUsers as $ou)
                    <flux:menu.item
                        :href="route('branch.switch', $ou->organization_id)"
                        :icon="session('current_org_id') == $ou->organization_id ? 'check' : 'building-office'"
                        wire:navigate
                    >
                        {{ $ou->organization?->name }}
                        @if($ou->organization?->isBranch())
                            <span class="ml-1 text-[10px] text-gray-400">branch</span>
                        @endif
                    </flux:menu.item>
                @endforeach
                <flux:menu.separator />
                <flux:menu.item :href="route('organization.branches')" icon="plus" wire:navigate>
                    Manage Branches
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>
    @elseif($currentOrg)
    <div class="mx-3 mb-2 px-2 py-1.5">
        <p class="truncate text-xs font-semibold text-gray-600 dark:text-gray-300">{{ $currentOrg->name }}</p>
    </div>
    @endif

    {{-- ── Collections ──────────────────────────────────────────────────────── --}}
    @if(in_array($branchRole, ['owner', 'manager']))
    <flux:sidebar.group
        id="tour-collections"
        :heading="__('Collections')"
        :expandable="true"
        :expanded="
            request()->routeIs('organization.payment.categories') ||
            request()->routeIs('organization.payments') ||
            request()->routeIs('reports.collections')
        "
    >
        <flux:sidebar.item
            icon="tag"
            :href="route('organization.payment.categories')"
            :current="request()->routeIs('organization.payment.categories')"
            wire:navigate
        >
            {{ __('Categories') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="currency-dollar"
            :href="route('organization.payments')"
            :current="request()->routeIs('organization.payments')"
            wire:navigate
        >
            {{ __('Collections') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="chart-bar"
            :href="route('reports.collections')"
            :current="request()->routeIs('reports.collections')"
            wire:navigate
        >
            {{ __('Report') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="arrow-up-tray"
            :href="route('organization.offline-payments')"
            :current="request()->routeIs('organization.offline-payments')"
            wire:navigate
        >
            {{ __('Offline Payments') }}
        </flux:sidebar.item>
    </flux:sidebar.group>
    @endif

    {{-- ── Pledges & Projects ───────────────────────────────────────────────── --}}
    @if(in_array($branchRole, ['owner', 'manager']))
    <flux:sidebar.group
        id="tour-pledges"
        :heading="__('Pledges & Projects')"
        :expandable="true"
        :expanded="
            request()->routeIs('organization.projects') ||
            request()->routeIs('organization.pledges') ||
            request()->routeIs('reports.pledges')
        "
    >
        <flux:sidebar.item
            icon="briefcase"
            :href="route('organization.projects')"
            :current="request()->routeIs('organization.projects')"
            wire:navigate
        >
            {{ __('Projects') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="clipboard-document-list"
            :href="route('organization.pledges')"
            :current="request()->routeIs('organization.pledges')"
            wire:navigate
        >
            {{ __('Pledges') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="chart-bar"
            :href="route('reports.pledges')"
            :current="request()->routeIs('reports.pledges')"
            wire:navigate
        >
            {{ __('Report') }}
        </flux:sidebar.item>
    </flux:sidebar.group>
    @endif

    {{-- ── Expenses ─────────────────────────────────────────────────────────── --}}
    @if(in_array($branchRole, ['owner', 'manager']))
    <flux:sidebar.group
        id="tour-expenses"
        :heading="__('Expenses')"
        :expandable="true"
        :expanded="
            request()->routeIs('organization.expense.categories') ||
            request()->routeIs('organization.expenses') ||
            request()->routeIs('reports.expenses')
        "
    >
        <flux:sidebar.item
            icon="folder"
            :href="route('organization.expense.categories')"
            :current="request()->routeIs('organization.expense.categories')"
            wire:navigate
        >
            {{ __('Categories') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="receipt-percent"
            :href="route('organization.expenses')"
            :current="request()->routeIs('organization.expenses')"
            wire:navigate
        >
            {{ __('Expenses') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="chart-bar"
            :href="route('reports.expenses')"
            :current="request()->routeIs('reports.expenses')"
            wire:navigate
        >
            {{ __('Report') }}
        </flux:sidebar.item>
    </flux:sidebar.group>
    @endif

    {{-- ── Treasurer Tools ──────────────────────────────────────────────────── --}}
    @if(in_array($branchRole, ['owner', 'manager']))
    <flux:sidebar.group
        id="tour-treasurer"
        :heading="__('Treasurer')"
        :expandable="true"
        :expanded="
            request()->routeIs('organization.budgets') ||
            request()->routeIs('organization.audit-log') ||
            request()->routeIs('organization.giving-statement')
        "
    >
        <flux:sidebar.item
            icon="calculator"
            :href="route('organization.budgets')"
            :current="request()->routeIs('organization.budgets')"
            wire:navigate
        >
            {{ __('Budgets') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="document-text"
            :href="route('organization.giving-statement')"
            :current="request()->routeIs('organization.giving-statement')"
            wire:navigate
        >
            {{ __('Giving Statements') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="shield-check"
            :href="route('organization.audit-log')"
            :current="request()->routeIs('organization.audit-log')"
            wire:navigate
        >
            {{ __('Audit Log') }}
        </flux:sidebar.item>
    </flux:sidebar.group>
    @endif

    {{-- ── Members ──────────────────────────────────────────────────────────── --}}
    @if(in_array($branchRole, ['owner', 'manager']))
    <flux:sidebar.group
        id="tour-organization"
        :heading="__('Organization')"
        :expandable="true"
        :expanded="request()->routeIs('organization.members')
        ||request()->routeIs('create.organization')
        ||request()->routeIs('subscription.plans')
        ||request()->routeIs('organization.branches')"
    >
        <flux:sidebar.item
            icon="users"
            :href="route('organization.members')"
            :current="request()->routeIs('organization.members')"
            wire:navigate
        >
            {{ __('Members') }}
        </flux:sidebar.item>
        @if($branchRole === 'owner')
         <flux:sidebar.item
            icon="currency-dollar"
            :href="route('subscription.plans')"
            :current="request()->routeIs('subscription.plans')"
            wire:navigate
        >
            {{ __('Subscriptions') }}
        </flux:sidebar.item>
         <flux:sidebar.item
            icon="building-office"
            :href="route('create.organization')"
            :current="request()->routeIs('create.organization')"
            wire:navigate
        >
            {{ __('Details & Admins') }}
        </flux:sidebar.item>
        <flux:sidebar.item
            icon="building-office-2"
            :href="route('organization.branches')"
            :current="request()->routeIs('organization.branches')"
            wire:navigate
        >
            {{ __('Branches') }}
        </flux:sidebar.item>

        <flux:sidebar.item
            icon="clipboard-document-check"
            :href="route('organization.offline-payment-review')"
            :current="request()->routeIs('organization.offline-payment-review')"
            wire:navigate
        >
            {{ __('Payment Review') }}
        </flux:sidebar.item>
        @endif
    </flux:sidebar.group>
    @endif

@endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            @if(auth()->user()->role !== 'admin')
            <flux:sidebar.nav>
                <flux:sidebar.item id="tour-trigger" icon="academic-cap" onclick="startTour()" class="cursor-pointer">
                    {{ __('Take the Tour') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
            @endif

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>


        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}
        <x-notifications/>
        @fluxScripts

        @if(auth()->user()->role !== 'admin')
        {{-- Driver.js onboarding tour --}}
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1/dist/driver.css"/>
        <script src="https://cdn.jsdelivr.net/npm/driver.js@1/dist/driver.js.iife.js"></script>
        <style>
            .driver-popover { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif !important; border-radius: 14px !important; box-shadow: 0 20px 60px rgba(0,0,0,0.18) !important; }
            .driver-popover-title { font-size: 15px !important; font-weight: 700 !important; color: #111827 !important; }
            .driver-popover-description { font-size: 13px !important; color: #6b7280 !important; line-height: 1.6 !important; }
            .driver-popover-progress-text { font-size: 11px !important; color: #9ca3af !important; }
            .driver-popover-next-btn { background: #2563eb !important; border-color: #2563eb !important; border-radius: 8px !important; font-size: 13px !important; padding: 7px 16px !important; }
            .driver-popover-next-btn:hover { background: #1d4ed8 !important; }
            .driver-popover-prev-btn { border-radius: 8px !important; font-size: 13px !important; padding: 7px 16px !important; color: #374151 !important; border-color: #d1d5db !important; }
            .driver-popover-close-btn { color: #9ca3af !important; }
        </style>
        <script>
            const __tourSteps = [
                {
                    popover: {
                        title: '👋 Welcome to The Treasurer!',
                        description: "Let's take a quick 2-minute tour so you know where everything is. You can skip anytime and come back to this tour from the sidebar.",
                        side: 'over',
                        align: 'center',
                    },
                },
                {
                    element: '#tour-dashboard',
                    popover: {
                        title: '📊 Dashboard',
                        description: 'Your home base. See this month\'s collections, expenses, pending pledges, and a monthly income vs expenses chart — all at a glance.',
                        side: 'right',
                    },
                },
                {
                    element: '#tour-collections',
                    popover: {
                        title: '💰 Collections',
                        description: 'Record Sunday offerings, tithes, and donations here. Each payment is linked to a member and a category. You can also generate a PDF receipt instantly.',
                        side: 'right',
                    },
                },
                {
                    element: '#tour-expenses',
                    popover: {
                        title: '🧾 Expenses',
                        description: 'Track all church expenditures — salaries, utilities, events, maintenance. Every expense is categorised and logged in the audit trail.',
                        side: 'right',
                    },
                },
                {
                    element: '#tour-pledges',
                    popover: {
                        title: '🤝 Pledges & Projects',
                        description: 'Members can pledge towards building projects or special drives. Track fulfilment progress and see who has overdue pledges right on the dashboard.',
                        side: 'right',
                    },
                },
                {
                    element: '#tour-treasurer',
                    popover: {
                        title: '📋 Treasurer Tools',
                        description: 'This is the heart of the platform. Set budgets, compare actual vs planned spend, generate annual giving statements for each member, and review the full audit log.',
                        side: 'right',
                    },
                },
                {
                    element: '#tour-organization',
                    popover: {
                        title: '🏛️ Organization',
                        description: 'Manage your members and their roles, set up branches for different church locations, and control your subscription plan — all from this section.',
                        side: 'right',
                    },
                },
                {
                    element: '#tour-trigger',
                    popover: {
                        title: '✅ You\'re all set!',
                        description: 'You can replay this tour anytime by clicking "Take the Tour" here. Start by recording your first collection or adding your members.',
                        side: 'right',
                    },
                },
            ];

            function startTour() {
                const { driver } = window.driver.js;
                const driverObj = driver({
                    showProgress: true,
                    progressText: 'Step @{{current}} of @{{total}}',
                    nextBtnText: 'Next →',
                    prevBtnText: '← Back',
                    doneBtnText: 'Get Started!',
                    animate: true,
                    overlayOpacity: 0.55,
                    steps: __tourSteps,
                    onDestroyStarted: () => {
                        markTourComplete();
                        driverObj.destroy();
                    },
                });
                driverObj.drive();
            }

            function markTourComplete() {
                fetch('{{ route('onboarding.complete') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                            ?? '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                });
            }

            @if(!auth()->user()->onboarding_completed_at)
            document.addEventListener('DOMContentLoaded', () => {
                // Small delay so the page fully renders before the tour starts
                setTimeout(startTour, 800);
            });
            @endif
        </script>
        @endif
    </body>
</html>
