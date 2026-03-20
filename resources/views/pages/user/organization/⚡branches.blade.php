<?php

use App\Models\Organization;
use App\Models\OrganizationPlan;
use App\Models\OrganizationUser;
use App\Models\PaymentCategory;
use App\Models\Plan;
use App\Models\User;
use Livewire\Component;
use WireUi\Traits\WireUiActions;

new class extends Component
{
    use WireUiActions;
    // Create branch form
    public string $name     = '';
    public string $currency = 'ZMW';
    public bool   $showForm = false;

    // Assign manager modal
    public bool   $showManagerModal  = false;
    public ?int   $managingBranchId  = null;
    public string $managerSearch     = '';
    public ?int   $selectedManagerId = null;

    // Add member modal
    public bool   $showMemberModal  = false;
    public ?int   $memberBranchId   = null;
    public string $memberSearch     = '';
    public ?int   $selectedMemberId = null;

    public function getParentOrgProperty(): Organization
    {
        $orgId = auth()->user()->myOrganization->organization_id;
        $org   = Organization::findOrFail($orgId);

        return $org->isBranch() ? $org->parent : $org;
    }

    public function getCurrentBranchRoleProperty(): string
    {
        return auth()->user()->myOrganization?->branch_role ?? 'member';
    }

    public function getBranchesProperty()
    {
        return $this->parentOrg
            ->branches()
            ->with(['organizationUsers.user'])
            ->withCount(['organizationUsers as member_count' => fn ($q) => $q->where('branch_role', 'member')])
            ->get();
    }

    /** Users in the parent org who can be picked as a manager for a branch */
    public function getManagerCandidatesProperty()
    {
        if (strlen($this->managerSearch) < 2) {
            return collect();
        }

        $existingInBranch = OrganizationUser::where('organization_id', $this->managingBranchId)->pluck('user_id');

        return User::where(function ($q) {
            $q->where('name', 'like', '%' . $this->managerSearch . '%')
                ->orWhere('email', 'like', '%' . $this->managerSearch . '%');
        })
            ->whereNotIn('id', $existingInBranch)
            ->limit(8)
            ->get();
    }

    /** Users in the parent org who can be added as members of a branch */
    public function getMemberCandidatesProperty()
    {
        if (strlen($this->memberSearch) < 2) {
            return collect();
        }

        $existingInBranch = OrganizationUser::where('organization_id', $this->memberBranchId)->pluck('user_id');

        return OrganizationUser::with('user')
            ->where('organization_id', $this->parentOrg->id)
            ->whereNotIn('user_id', $existingInBranch)
            ->whereHas('user', function ($q) {
                $q->where('name', 'like', '%' . $this->memberSearch . '%')
                    ->orWhere('email', 'like', '%' . $this->memberSearch . '%');
            })
            ->limit(8)
            ->get()
            ->pluck('user');
    }

    public function openForm(): void
    {
        $this->reset('name', 'currency');
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name'     => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:10'],
        ]);

        $parent = $this->parentOrg;

        $branch = Organization::create([
            'parent_id' => $parent->id,
            'owner_id'  => $parent->owner_id,
            'name'      => $this->name,
            'currency'  => $this->currency,
            'slug'      => \Illuminate\Support\Str::slug($this->name . '-' . time()),
        ]);

        OrganizationUser::create([
            'user_id'         => auth()->id(),
            'organization_id' => $branch->id,
            'user_type'       => 'admin',
            'branch_role'     => 'owner',
        ]);

        $trialPlan = Plan::trial()->where('is_active', true)->first();

        if ($trialPlan) {
            OrganizationPlan::create([
                'organization_id' => $branch->id,
                'plan_id'         => $trialPlan->id,
                'start_date'      => now(),
                'end_date'        => now()->addDays($trialPlan->trial_days ?? 3),
                'is_active'       => true,
            ]);
        }

        $categories = [
            'Offering',
            'Tithe',
            'Pledges',
            'Donation'
        ];

        foreach ($categories as $category) {
            PaymentCategory::firstOrCreate(
                [
                    'name' => $category,
                    'organization_id' => $branch->id
                ],
                [
                    'is_active' => true
                ]
            );
        }

        $this->showForm = false;
        $this->reset('name', 'currency');
        $this->notification()->success('Branch created', "{$branch->name} has been set up with a free trial.");
    }

    // ── Manager assignment ────────────────────────────────────────────────

    public function openManagerModal(int $branchId): void
    {
        $this->managingBranchId  = $branchId;
        $this->managerSearch     = '';
        $this->selectedManagerId = null;
        $this->showManagerModal  = true;
    }

    public function selectManager(int $userId): void
    {
        $this->selectedManagerId = $userId;
        $user                    = User::find($userId);
        $this->managerSearch     = $user?->name ?? '';
    }

    public function assignManager(): void
    {
        $this->validate(['selectedManagerId' => ['required', 'exists:users,id']]);

        $branch = Organization::findOrFail($this->managingBranchId);

        // Demote any existing manager in this branch
        OrganizationUser::where('organization_id', $branch->id)
            ->where('branch_role', 'manager')
            ->update(['branch_role' => 'member']);

        // Upsert: add user to branch or update their role
        $existing = OrganizationUser::where('organization_id', $branch->id)
            ->where('user_id', $this->selectedManagerId)
            ->first();

        if ($existing) {
            $existing->update(['branch_role' => 'manager']);
        } else {
            OrganizationUser::create([
                'user_id'         => $this->selectedManagerId,
                'organization_id' => $branch->id,
                'user_type'       => 'member',
                'branch_role'     => 'manager',
            ]);
        }

        $this->showManagerModal  = false;
        $this->managingBranchId  = null;
        $this->selectedManagerId = null;
        $this->managerSearch     = '';
        $this->notification()->success('Manager assigned', 'Branch manager has been updated.');
    }

    public function removeManager(int $branchId): void
    {
        OrganizationUser::where('organization_id', $branchId)
            ->where('branch_role', 'manager')
            ->update(['branch_role' => 'member']);

        $this->notification()->success('Removed', 'Branch manager has been removed.');
    }

    // ── Member management ─────────────────────────────────────────────────

    public function openMemberModal(int $branchId): void
    {
        $this->memberBranchId   = $branchId;
        $this->memberSearch     = '';
        $this->selectedMemberId = null;
        $this->showMemberModal  = true;
    }

    public function selectMember(int $userId): void
    {
        $this->selectedMemberId = $userId;
        $user                   = User::find($userId);
        $this->memberSearch     = $user?->name ?? '';
    }

    public function addMember(): void
    {
        $this->validate(['selectedMemberId' => ['required', 'exists:users,id']]);

        $alreadyIn = OrganizationUser::where('organization_id', $this->memberBranchId)
            ->where('user_id', $this->selectedMemberId)
            ->exists();

        if (! $alreadyIn) {
            OrganizationUser::create([
                'user_id'         => $this->selectedMemberId,
                'organization_id' => $this->memberBranchId,
                'user_type'       => 'member',
                'branch_role'     => 'member',
            ]);
        }

        $this->showMemberModal  = false;
        $this->memberBranchId   = null;
        $this->selectedMemberId = null;
        $this->memberSearch     = '';
        $this->notification()->success('Added', 'Member added to branch.');
    }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Branch Management</h1>
            <p class="mt-1 text-sm text-gray-500">
                Manage branches under <span class="font-semibold">{{ $this->parentOrg->name }}</span>
            </p>
        </div>
        @if($this->currentBranchRole === 'owner')
            <flux:button wire:click="openForm" icon="plus" variant="primary">Add Branch</flux:button>
        @endif
    </div>

    {{-- Create branch form --}}
    @if($showForm)
    <div class="mb-6 rounded-xl border border-indigo-100 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-base font-semibold text-gray-900">New Branch</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Branch Name</flux:label>
                <flux:input wire:model="name" placeholder="e.g. Lusaka Branch" />
                <flux:error name="name" />
            </flux:field>
            <flux:field>
                <flux:label>Currency</flux:label>
                <flux:select wire:model="currency">
                    <option value="ZMW">ZMW — Zambian Kwacha</option>
                    <option value="USD">USD — US Dollar</option>
                    <option value="ZAR">ZAR — South African Rand</option>
                    <option value="KES">KES — Kenyan Shilling</option>
                    <option value="NGN">NGN — Nigerian Naira</option>
                    <option value="GHS">GHS — Ghanaian Cedi</option>
                </flux:select>
                <flux:error name="currency" />
            </flux:field>
        </div>
        <div class="mt-4 flex gap-3">
            <flux:button wire:click="save" variant="primary" wire:loading.attr="disabled">Create Branch</flux:button>
            <flux:button wire:click="$set('showForm', false)" variant="ghost">Cancel</flux:button>
        </div>
    </div>
    @endif

    {{-- HQ Card --}}
    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-sm font-bold text-white">
                    {{ strtoupper(substr($this->parentOrg->name, 0, 2)) }}
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <p class="font-semibold text-gray-900">{{ $this->parentOrg->name }}</p>
                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">Headquarters</span>
                    </div>
                    <p class="text-xs text-gray-400">{{ $this->parentOrg->currency ?? 'ZMW' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                @php $ap = $this->parentOrg->activePlan?->plan; @endphp
                @if($ap)
                    <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">{{ $ap->name }}</span>
                @endif
                @if(session('current_org_id') != $this->parentOrg->id)
                    <a href="{{ route('branch.switch', $this->parentOrg->id) }}"
                       class="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors">
                        Switch to HQ
                    </a>
                @else
                    <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-600">Active</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Branches --}}
    @forelse($this->branches as $branch)
        @php
            $manager = $branch->organizationUsers->firstWhere('branch_role', 'manager')?->user;
            $owner   = $branch->organizationUsers->firstWhere('branch_role', 'owner')?->user;
            $ap      = $branch->activePlan?->plan;
        @endphp

        <div class="mb-4 rounded-xl border border-gray-200 bg-white shadow-sm" wire:key="branch-{{ $branch->id }}">

            {{-- Branch header --}}
            <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-100 text-sm font-bold text-purple-700">
                        {{ strtoupper(substr($branch->name, 0, 2)) }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-gray-900">{{ $branch->name }}</p>
                            <span class="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">Branch</span>
                        </div>
                        <p class="text-xs text-gray-400">{{ $branch->currency ?? 'ZMW' }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($ap)
                        <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">{{ $ap->name }}</span>
                    @endif
                    @if($this->currentBranchRole === 'owner')
                        <flux:button wire:click="openManagerModal({{ $branch->id }})" size="sm" variant="ghost" icon="user-plus">
                            {{ $manager ? 'Change Manager' : 'Assign Manager' }}
                        </flux:button>
                        <flux:button wire:click="openMemberModal({{ $branch->id }})" size="sm" variant="ghost" icon="user-plus">
                            Add Member
                        </flux:button>
                    @endif
                    @if(session('current_org_id') == $branch->id)
                        <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-600">Active</span>
                    @else
                        <a href="{{ route('branch.switch', $branch->id) }}"
                           class="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors">
                            Switch
                        </a>
                    @endif
                </div>
            </div>

            {{-- Branch people --}}
            <div class="border-t border-gray-100 px-5 py-3 flex flex-wrap gap-6 text-sm">

                {{-- Owner --}}
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-400 font-medium uppercase tracking-wide">Owner</span>
                    @if($owner)
                        <div class="flex items-center gap-1.5">
                            <div class="h-5 w-5 rounded-full bg-indigo-200 flex items-center justify-center text-[10px] font-bold text-indigo-800">
                                {{ strtoupper(substr($owner->name, 0, 1)) }}
                            </div>
                            <span class="text-gray-700 text-xs">{{ $owner->name }}</span>
                        </div>
                    @else
                        <span class="text-xs text-gray-400">—</span>
                    @endif
                </div>

                {{-- Manager --}}
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-400 font-medium uppercase tracking-wide">Manager</span>
                    @if($manager)
                        <div class="flex items-center gap-1.5">
                            <div class="h-5 w-5 rounded-full bg-amber-200 flex items-center justify-center text-[10px] font-bold text-amber-800">
                                {{ strtoupper(substr($manager->name, 0, 1)) }}
                            </div>
                            <span class="text-gray-700 text-xs">{{ $manager->name }}</span>
                            @if($this->currentBranchRole === 'owner')
                                <button wire:click="removeManager({{ $branch->id }})"
                                        class="text-xs text-red-400 hover:text-red-600 transition-colors ml-1">Remove</button>
                            @endif
                        </div>
                    @else
                        <span class="text-xs text-gray-400 italic">No manager assigned</span>
                    @endif
                </div>

                {{-- Members count --}}
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-400 font-medium uppercase tracking-wide">Members</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">{{ $branch->member_count }}</span>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-gray-200 bg-white p-16 text-center shadow-sm">
            <div class="flex flex-col items-center gap-2 text-gray-400">
                <x-icon name="building-office-2" class="h-10 w-10 opacity-40" />
                <p class="text-sm">No branches yet. Add your first branch above.</p>
            </div>
        </div>
    @endforelse

    {{-- Assign Manager Modal --}}
    <flux:modal wire:model="showManagerModal" name="assign-manager-modal">
        <div class="space-y-4 p-6">
            <flux:heading size="lg">Assign Branch Manager</flux:heading>
            <flux:text>Search for a user to assign as the manager of this branch. They will be able to manage all collections, members, and expenses for this branch.</flux:text>

            <flux:field>
                <flux:label>Search user by name or email</flux:label>
                <flux:input wire:model.live.debounce.300ms="managerSearch" placeholder="Type at least 2 characters…" />
            </flux:field>

            @if($managerSearch && $this->managerCandidates->count() > 0)
                <div class="rounded-lg border border-gray-200 divide-y divide-gray-100 overflow-hidden">
                    @foreach($this->managerCandidates as $candidate)
                        <button wire:click="selectManager({{ $candidate->id }})"
                                class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-indigo-50 transition-colors
                                    {{ $selectedManagerId === $candidate->id ? 'bg-indigo-50' : '' }}">
                            <div class="h-7 w-7 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-700 flex-shrink-0">
                                {{ strtoupper(substr($candidate->name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $candidate->name }}</p>
                                <p class="text-xs text-gray-400">{{ $candidate->email }}</p>
                            </div>
                            @if($selectedManagerId === $candidate->id)
                                <x-icon name="check-circle" class="ml-auto h-4 w-4 text-indigo-600 flex-shrink-0" />
                            @endif
                        </button>
                    @endforeach
                </div>
            @elseif(strlen($managerSearch) >= 2 && $this->managerCandidates->isEmpty())
                <p class="text-sm text-gray-400 text-center py-2">No users found matching "{{ $managerSearch }}"</p>
            @endif

            <flux:error name="selectedManagerId" />

            <div class="flex gap-3 justify-end pt-2">
                <flux:button wire:click="$set('showManagerModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="assignManager" variant="primary" :disabled="!$selectedManagerId">
                    Assign Manager
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Add Member Modal --}}
    <flux:modal wire:model="showMemberModal" name="add-member-modal">
        <div class="space-y-4 p-6">
            <flux:heading size="lg">Add Member to Branch</flux:heading>
            <flux:text>Search for an existing member in your organisation to add to this branch.</flux:text>

            <flux:field>
                <flux:label>Search member by name or email</flux:label>
                <flux:input wire:model.live.debounce.300ms="memberSearch" placeholder="Type at least 2 characters…" />
            </flux:field>

            @if($memberSearch && $this->memberCandidates->count() > 0)
                <div class="rounded-lg border border-gray-200 divide-y divide-gray-100 overflow-hidden">
                    @foreach($this->memberCandidates as $candidate)
                        <button wire:click="selectMember({{ $candidate->id }})"
                                class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-indigo-50 transition-colors
                                    {{ $selectedMemberId === $candidate->id ? 'bg-indigo-50' : '' }}">
                            <div class="h-7 w-7 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-700 flex-shrink-0">
                                {{ strtoupper(substr($candidate->name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $candidate->name }}</p>
                                <p class="text-xs text-gray-400">{{ $candidate->email }}</p>
                            </div>
                            @if($selectedMemberId === $candidate->id)
                                <x-icon name="check-circle" class="ml-auto h-4 w-4 text-indigo-600 flex-shrink-0" />
                            @endif
                        </button>
                    @endforeach
                </div>
            @elseif(strlen($memberSearch) >= 2 && $this->memberCandidates->isEmpty())
                <p class="text-sm text-gray-400 text-center py-2">No members found matching "{{ $memberSearch }}"</p>
            @endif

            <flux:error name="selectedMemberId" />

            <div class="flex gap-3 justify-end pt-2">
                <flux:button wire:click="$set('showMemberModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="addMember" variant="primary" :disabled="!$selectedMemberId">
                    Add to Branch
                </flux:button>
            </div>
        </div>
    </flux:modal>
    <x-spinner/>
</div>
