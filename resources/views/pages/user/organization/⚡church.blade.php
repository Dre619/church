<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Organization;
use App\Models\OrganizationPlan;
use App\Models\OrganizationUser;
use App\Models\PaymentCategory;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use WireUi\Traits\WireUiActions;

new class extends Component
{
    use WithFileUploads;
    use WireUiActions;


    // ── Tabs ──────────────────────────────────────────────────────────────
    public string $activeTab = 'organization'; // 'organization' | 'members' | 'settings'

    // ── Organization form ─────────────────────────────────────────────────
    public ?int    $organizationId = null;
    public string  $name     = '';
    public string  $website  = '';
    public string  $address  = '';
    public string  $phone    = '';
    public string  $email    = '';
    public         $logo     = null;     // Livewire temp upload
    public ?string $logoUrl  = null;     // persisted logo path

    // ── Settings form ─────────────────────────────────────────────────────
    public string $currency = 'ZMW';

    // ── Member form ───────────────────────────────────────────────────────
    public bool   $showMemberModal  = false;
    public bool   $editingMember    = false;
    public ?int   $editingMemberId  = null;
    public string $memberName       = '';
    public string $memberEmail      = '';
    public string $memberPassword   = '';
    public bool   $confirmingDelete = false;
    public ?int   $deletingMemberId = null;

    // ── Lifecycle ─────────────────────────────────────────────────────────
    public function mount(): void
    {
        $org = $this->myOrganization();

        if ($org) {
            $this->organizationId = $org->id;
            $this->name     = $org->name;
            $this->website  = $org->website  ?? '';
            $this->address  = $org->address  ?? '';
            $this->phone    = $org->phone    ?? '';
            $this->email    = $org->email    ?? '';
            $this->logoUrl  = $org->logo     ?? null;
            $this->currency = $org->currency ?? 'ZMW';
            $this->activeTab = 'members';
        }
    }

    // ── Computed helpers ──────────────────────────────────────────────────
    private function myOrganization(): ?Organization
    {
        return Organization::where('owner_id', auth()->id())->first();
    }

    public function getOrganizationProperty(): ?Organization
    {
        return $this->organizationId
            ? Organization::find($this->organizationId)
            : null;
    }

    public function getMembersProperty()
    {
        if (! $this->organizationId) return collect();

        return OrganizationUser::with('user')
            ->where('organization_id', $this->organizationId)
            ->where('user_id', '!=', auth()->id())   // exclude owner
            ->latest()
            ->get();
    }

    // ── Organization CRUD ─────────────────────────────────────────────────
    public function saveOrganization(): void
    {
        $this->validate([
            'name'    => 'required|string|max:255',
            'email'   => ['nullable','email', Rule::unique('organizations','email')->ignore($this->organizationId)],
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string|max:500',
            'phone'   => 'nullable|string|max:50',
            'logo'    => 'nullable|image|max:2048',
        ]);

        $data = [
            'owner_id' => auth()->id(),
            'name'     => $this->name,
            'slug'     => Str::slug($this->name),
            'website'  => $this->website  ?: null,
            'address'  => $this->address  ?: null,
            'phone'    => $this->phone    ?: null,
            'email'    => $this->email    ?: null,
        ];

        if ($this->logo) {
            $data['logo'] = $this->logo->store('logos', 'public');
            $this->logoUrl = $data['logo'];
        }

        DB::transaction(function () use ($data) {
            $isNew = is_null($this->organizationId);

            $org = Organization::updateOrCreate(
                ['id' => $this->organizationId],
                $data
            );
            $this->organizationId = $org->id;

            // Link owner as OrganizationUser (if not already)
            OrganizationUser::firstOrCreate([
                'user_id'         => auth()->id(),
                'organization_id' => $org->id,
                'user_type'       => 'manager',
            ], [
                'branch_role' => 'owner',
            ]);

            // Auto-assign the free trial plan on first creation
            if ($isNew) {
                $trial = Plan::trial()->where('is_active', true)->first();

                if ($trial) {
                    OrganizationPlan::create([
                        'organization_id' => $org->id,
                        'plan_id'         => $trial->id,
                        'start_date'      => now(),
                        'end_date'        => now()->addDays($trial->trial_days),
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
                            'organization_id' => $org->id
                        ],
                        [
                            'is_active' => true
                        ]
                    );
                }
            }
        });

        $this->logo = null;
        $this->activeTab = 'members';

        $this->notification([
            'title'       => 'Organization saved!',
            'description' => 'Your church profile has been updated.',
            'icon'        => 'check-circle',
            'iconColor'   => 'text-amber-500',
        ]);
    }

    // ── Currency / Settings ───────────────────────────────────────────────
    public function saveCurrency(): void
    {
        $this->validate(['currency' => 'required|string|max:10|in:' . implode(',', array_keys(currency_list()))]);

        Organization::where('id', $this->organizationId)->update(['currency' => $this->currency]);

        $this->notification([
            'title'       => 'Currency updated!',
            'description' => 'The system currency has been saved.',
            'icon'        => 'check-circle',
            'iconColor'   => 'text-green-500',
        ]);
    }

    // ── Member CRUD ───────────────────────────────────────────────────────
    public function openAddMember(): void
    {
        $this->resetMemberForm();
        $this->editingMember = false;
        $this->showMemberModal = true;
    }

    public function openEditMember(int $orgUserId): void
    {
        $ou = OrganizationUser::with('user')->findOrFail($orgUserId);
        $this->editingMemberId = $ou->id;
        $this->memberName      = $ou->user->name;
        $this->memberEmail     = $ou->user->email;
        $this->memberPassword  = '';
        $this->editingMember   = true;
        $this->showMemberModal = true;
    }

    public function saveMember(): void
    {
        $rules = [
            'memberName'  => 'required|string|max:255',
            'memberEmail' => ['required','email',
                $this->editingMember
                    ? Rule::unique('users','email')->ignore(
                        OrganizationUser::find($this->editingMemberId)?->user_id
                      )
                    : Rule::unique('users','email'),
            ],
            'memberPassword' => $this->editingMember ? 'nullable|min:8' : 'required|min:8',
        ];

        $this->validate($rules, [], [
            'memberName'     => 'name',
            'memberEmail'    => 'email',
            'memberPassword' => 'password',
        ]);

        // Enforce member limit before adding a new member
        if (! $this->editingMember) {
            $plan = active_plan();

            if ($plan && $plan->max_members !== null) {
                $currentCount = OrganizationUser::where('organization_id', $this->organizationId)->count();

                if ($currentCount >= $plan->max_members) {
                    $this->notification([
                        'title'       => 'Member limit reached',
                        'description' => "Your {$plan->name} plan allows up to {$plan->max_members} members. Please upgrade to add more.",
                        'icon'        => 'exclamation-circle',
                        'iconColor'   => 'text-red-500',
                    ]);

                    return;
                }
            }
        }

        DB::transaction(function () {
            if ($this->editingMember) {
                $ou   = OrganizationUser::findOrFail($this->editingMemberId);
                $user = $ou->user;
                $user->name  = $this->memberName;
                $user->email = $this->memberEmail;
                if ($this->memberPassword) {
                    $user->password = Hash::make($this->memberPassword);
                }
                $user->save();
            } else {
                $user = User::create([
                    'name'     => $this->memberName,
                    'email'    => $this->memberEmail,
                    'password' => Hash::make($this->memberPassword),
                ]);

                OrganizationUser::create([
                    'user_id'         => $user->id,
                    'organization_id' => $this->organizationId,
                    'user_type'       => 'manager',
                ]);
            }
        });

        $this->showMemberModal = false;
        $this->resetMemberForm();

        $this->notification([
            'title'       => $this->editingMember ? 'Member updated!' : 'Member added!',
            'description' => $this->editingMember
                ? 'The member\'s details have been updated.'
                : 'A new member has been added to your church.',
            'icon'        => 'user-add',
            'iconColor'   => 'text-amber-500',
        ]);
    }

    public function confirmDelete(int $orgUserId): void
    {
        $this->deletingMemberId = $orgUserId;
        $this->confirmingDelete = true;
    }

    public function deleteMember(): void
    {
        $ou = OrganizationUser::findOrFail($this->deletingMemberId);
        $ou->user->delete();   // cascades; adjust if soft-deletes preferred
        $ou->delete();

        $this->confirmingDelete = false;
        $this->deletingMemberId = null;

        $this->notification([
            'title'     => 'Member removed.',
            'icon'      => 'trash',
            'iconColor' => 'text-red-400',
        ]);
    }

    private function resetMemberForm(): void
    {
        $this->memberName      = '';
        $this->memberEmail     = '';
        $this->memberPassword  = '';
        $this->editingMember   = false;
        $this->editingMemberId = null;
    }
};
?>

<div class="min-h-screen bg-stone-50" style="font-family: 'Lora', 'Georgia', serif;">

    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        .sans { font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif; }

        .tab-ink {
            position: relative;
            transition: color 0.2s ease;
        }
        .tab-ink::after {
            content: '';
            position: absolute;
            bottom: -1px; left: 0; right: 0;
            height: 2px;
            background: #92400e;
            transform: scaleX(0);
            transition: transform 0.25s ease;
        }
        .tab-ink.active::after { transform: scaleX(1); }
        .tab-ink.active { color: #92400e; }

        .field-line {
            border: none;
            border-bottom: 1.5px solid #d6cfc6;
            border-radius: 0;
            background: transparent;
            padding: 0.45rem 0;
            transition: border-color 0.2s ease;
            outline: none;
            width: 100%;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            color: #1c1917;
        }
        .field-line::placeholder { color: #a8a29e; }
        .field-line:focus { border-bottom-color: #92400e; }

        .field-label {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #78716c;
            margin-bottom: 2px;
            display: block;
        }

        .btn-primary {
            background: #92400e;
            color: #fef3c7;
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
            padding: 0.65rem 1.75rem;
            border-radius: 2px;
            border: none;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.1s ease;
        }
        .btn-primary:hover { background: #78350f; transform: translateY(-1px); }
        .btn-primary:active { transform: translateY(0); }

        .btn-ghost {
            background: transparent;
            color: #78716c;
            font-family: 'DM Sans', sans-serif;
            font-weight: 500;
            font-size: 0.85rem;
            padding: 0.65rem 1.25rem;
            border-radius: 2px;
            border: 1.5px solid #d6cfc6;
            cursor: pointer;
            transition: border-color 0.2s, color 0.2s;
        }
        .btn-ghost:hover { border-color: #92400e; color: #92400e; }

        .member-row {
            transition: background 0.15s ease;
        }
        .member-row:hover { background: #fdf8f0; }

        @keyframes slide-up {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-slide-up { animation: slide-up 0.35s ease forwards; }

        .ornament {
            color: #c4a882;
            font-size: 1.4rem;
            line-height: 1;
        }

        .logo-drop {
            border: 1.5px dashed #c4a882;
            background: #fdf8f0;
            transition: border-color 0.2s, background 0.2s;
        }
        .logo-drop:hover { border-color: #92400e; background: #fef3c7; }
    </style>

    <div class="max-w-3xl mx-auto px-4 py-12 sm:px-6">

        {{-- ── Page Header ────────────────────────────────────────────────── --}}
        <div class="text-center mb-10 animate-slide-up">
            <div class="ornament mb-3">✦</div>
            <h1 class="text-3xl sm:text-4xl font-semibold text-stone-800 leading-tight">
                @if($organizationId)
                    {{ $name }}
                @else
                    Register Your Church
                @endif
            </h1>
            @if($organizationId)
            <p class="sans text-stone-500 text-sm mt-2">Manage your church profile and congregation members</p>
            @else
            <p class="sans text-stone-500 text-sm mt-2 italic">Set up your church's profile to get started</p>
            @endif
        </div>

        {{-- ── Tabs (only when org exists) ───────────────────────────────── --}}
        @if($organizationId)
        <div class="flex gap-8 border-b border-stone-200 mb-8">
            <button
                class="tab-ink sans text-sm font-medium pb-3 {{ $activeTab === 'organization' ? 'active text-amber-900' : 'text-stone-500' }}"
                wire:click="$set('activeTab','organization')">
                Church Profile
            </button>
            <button
                class="tab-ink sans text-sm font-medium pb-3 {{ $activeTab === 'members' ? 'active text-amber-900' : 'text-stone-500' }}"
                wire:click="$set('activeTab','members')">
                Members
                <span class="sans ml-1.5 text-xs bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded-full">
                    {{ $this->members->count() }}
                </span>
            </button>
            <button
                class="tab-ink sans text-sm font-medium pb-3 {{ $activeTab === 'settings' ? 'active text-amber-900' : 'text-stone-500' }}"
                wire:click="$set('activeTab','settings')">
                Settings
            </button>
        </div>
        @endif

        {{-- ══════════════════════════════════════════════════════════════════
             TAB: ORGANIZATION
        ══════════════════════════════════════════════════════════════════ --}}
        @if($activeTab === 'organization')
        <div class="animate-slide-up">
            <form wire:submit.prevent="saveOrganization">
                <div class="bg-white border border-stone-200 rounded-sm shadow-sm p-8 space-y-7">

                    {{-- Logo upload --}}
                    <div>
                        <label class="field-label">Church Logo</label>
                        <div class="mt-2 flex items-center gap-5">
                            <div class="w-20 h-20 rounded-full overflow-hidden bg-stone-100 border border-stone-200 flex items-center justify-center flex-shrink-0">
                                @if($logo)
                                    <img src="{{ $logo->temporaryUrl() }}" class="w-full h-full object-cover">
                                @elseif($logoUrl)
                                    <img src="{{ Storage::url($logoUrl) }}" class="w-full h-full object-cover">
                                @else
                                    <x-icon name="photo" class="w-8 h-8 text-stone-300" />
                                @endif
                            </div>
                            <label class="logo-drop cursor-pointer rounded-sm px-5 py-4 text-center flex-1">
                                <x-icon name="arrow-down" class="w-5 h-5 text-amber-700 mx-auto mb-1" />
                                <span class="sans text-xs text-stone-500 block">
                                    {{ $logo ? $logo->getClientOriginalName() : 'Click to upload logo' }}
                                </span>
                                <input type="file" wire:model="logo" accept="image/*" class="hidden">
                            </label>
                        </div>
                        @error('logo') <p class="sans text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-7">
                        {{-- Name --}}
                        <div class="sm:col-span-2">
                            <label class="field-label">Church Name <span class="text-red-400">*</span></label>
                            <input wire:model="name" type="text" placeholder="Grace Community Church" class="field-line">
                            @error('name') <p class="sans text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Email --}}
                        <div>
                            <label class="field-label">Contact Email</label>
                            <input wire:model="email" type="email" placeholder="info@church.org" class="field-line">
                            @error('email') <p class="sans text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Phone --}}
                        <div>
                            <label class="field-label">Phone</label>
                            <input wire:model="phone" type="text" placeholder="+260......" class="field-line">
                            @error('phone') <p class="sans text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Website --}}
                        <div>
                            <label class="field-label">Website</label>
                            <input wire:model="website" type="url" placeholder="https://yourchurch.org" class="field-line">
                            @error('website') <p class="sans text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Address --}}
                        <div>
                            <label class="field-label">Address</label>
                            <input wire:model="address" type="text" placeholder="123 Faith Ave, City, State" class="field-line">
                            @error('address') <p class="sans text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove>{{ $organizationId ? 'Save Changes' : 'Create Church' }}</span>
                            <span wire:loading class="sans">Saving…</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        @endif

        {{-- ══════════════════════════════════════════════════════════════════
             TAB: MEMBERS
        ══════════════════════════════════════════════════════════════════ --}}
        @if($activeTab === 'members')
        <div class="animate-slide-up">

            {{-- Toolbar --}}
            <div class="flex items-center justify-between mb-5">
                <p class="sans text-sm text-stone-500">
                    {{ $this->members->count() }} member{{ $this->members->count() !== 1 ? 's' : '' }} in your congregation
                </p>
                <button class="btn-primary" wire:click="openAddMember">
                    + Add Member
                </button>
            </div>

            {{-- Members list --}}
            <div class="bg-white border border-stone-200 rounded-sm shadow-sm overflow-hidden">
                @forelse($this->members as $orgUser)
                <div class="member-row flex items-center gap-4 px-6 py-4 border-b border-stone-100 last:border-0">
                    {{-- Avatar --}}
                    <div class="w-10 h-10 rounded-full bg-amber-100 border border-amber-200 flex items-center justify-center flex-shrink-0">
                        <span class="sans text-sm font-semibold text-amber-800">
                            {{ strtoupper(substr($orgUser->user->name, 0, 1)) }}
                        </span>
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-stone-800 font-medium text-sm truncate">{{ $orgUser->user->name }}</p>
                        <p class="sans text-stone-400 text-xs truncate">{{ $orgUser->user->email }}</p>
                    </div>

                    {{-- Date --}}
                    <p class="sans text-stone-300 text-xs hidden sm:block">
                        {{ $orgUser->created_at->format('M j, Y') }}
                    </p>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button
                            wire:click="openEditMember({{ $orgUser->id }})"
                            class="p-1.5 rounded text-stone-400 hover:text-amber-700 hover:bg-amber-50 transition-colors"
                            title="Edit member">
                            <x-icon name="pencil" class="w-4 h-4" />
                        </button>
                        <button
                            wire:click="confirmDelete({{ $orgUser->id }})"
                            class="p-1.5 rounded text-stone-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                            title="Remove member">
                            <x-icon name="trash" class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                @empty
                <div class="px-6 py-16 text-center">
                    <div class="ornament mb-3">✦</div>
                    <p class="text-stone-500 text-sm italic">No members yet. Add your congregation.</p>
                </div>
                @endforelse
            </div>
        </div>
        @endif

    </div>

        {{-- ══════════════════════════════════════════════════════════════════
             TAB: SETTINGS
        ══════════════════════════════════════════════════════════════════ --}}
        @if($activeTab === 'settings')
        <div class="animate-slide-up">
            <div class="bg-white border border-stone-200 rounded-sm shadow-sm p-8 space-y-8">

                {{-- ── Currency ───────────────────────────────────────── --}}
                <div>
                    <h3 class="text-base font-semibold text-stone-800 mb-1">System Currency</h3>
                    <p class="sans text-sm text-stone-500 mb-4">
                        This currency will appear on receipts and throughout the system.
                        Currently: <strong>{{ $currency }}</strong>
                        ({{ currency_list()[$currency] ?? $currency }})
                    </p>

                    <div class="space-y-4">
                        <div>
                            <label class="field-label">Select Currency</label>
                            <select wire:model="currency" class="field-line">
                                @foreach(currency_list() as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('currency') <p class="sans text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Preview --}}
                        <div class="rounded-md bg-stone-50 border border-stone-200 px-4 py-3">
                            <p class="sans text-xs text-stone-400 mb-1 uppercase tracking-wider font-semibold">Preview</p>
                            <p class="text-lg font-semibold text-stone-700">
                                {{ format_currency(1250.00, $currency) }}
                            </p>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="button" class="btn-primary" wire:click="saveCurrency" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveCurrency">Save Currency</span>
                                <span wire:loading wire:target="saveCurrency" class="sans">Saving…</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        @endif
{{-- ══════════════════════════════════════════════════════════════════════
         MODAL: Add / Edit Member
    ══════════════════════════════════════════════════════════════════════ --}}
    <x-modal wire:model="showMemberModal" max-width="lg" persistent>
        <x-card class="relative">
            <div class="p-2">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-full bg-amber-100 border border-amber-200 flex items-center justify-center">
                        <x-icon name="{{ $editingMember ? 'pencil' : 'user-plus' }}" class="w-5 h-5 text-amber-800" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-stone-800">
                            {{ $editingMember ? 'Edit Member' : 'Add New Member' }}
                        </h3>
                        <p class="sans text-xs text-stone-400">
                            {{ $editingMember ? 'Update member details' : 'Create an account for a new member' }}
                        </p>
                    </div>
                </div>

                <div class="space-y-5">
                    <div>
                        <label class="field-label">Full Name <span class="text-red-400">*</span></label>
                        <input wire:model="memberName" type="text" placeholder="Jane Doe" class="field-line">
                        @error('memberName') <p class="sans text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="field-label">Email Address <span class="text-red-400">*</span></label>
                        <input wire:model="memberEmail" type="email" placeholder="jane@example.com" class="field-line">
                        @error('memberEmail') <p class="sans text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="field-label">
                            Password
                            @if($editingMember)<span class="normal-case font-normal text-stone-400 ml-1">(leave blank to keep current)</span>@else<span class="text-red-400">*</span>@endif
                        </label>
                        <input wire:model="memberPassword" type="password" placeholder="Min. 8 characters" class="field-line">
                        @error('memberPassword') <p class="sans text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex gap-3 mt-7">
                    <button class="btn-ghost flex-1" wire:click="$set('showMemberModal', false)">Cancel</button>
                    <button class="btn-primary flex-1" wire:click="saveMember" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveMember">
                            {{ $editingMember ? 'Save Changes' : 'Add Member' }}
                        </span>
                        <span wire:loading wire:target="saveMember" class="sans">Saving…</span>
                    </button>
                </div>
            </div>
        </x-card>
    </x-modal>

    {{-- ══════════════════════════════════════════════════════════════════════
         MODAL: Confirm Delete
    ══════════════════════════════════════════════════════════════════════ --}}
    <x-modal wire:model="confirmingDelete" max-width="lg" persistent>
        <x-card class="relative">
            <div class="p-2 text-center">
                <div class="w-14 h-14 rounded-full bg-red-50 border border-red-100 flex items-center justify-center mx-auto mb-4">
                    <x-icon name="exclamation-triangle" class="w-7 h-7 text-red-400" />
                </div>
                <h3 class="font-semibold text-stone-800 mb-1">Remove Member?</h3>
                <p class="sans text-sm text-stone-500 mb-6">
                    This will permanently delete their account and remove them from your church.
                </p>
                <div class="flex gap-3">
                    <button class="btn-ghost flex-1" wire:click="$set('confirmingDelete', false)">Cancel</button>
                    <button
                        class="flex-1 sans font-semibold text-sm px-4 py-2.5 rounded-sm bg-red-500 hover:bg-red-600 text-white transition-colors"
                        wire:click="deleteMember"
                        wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="deleteMember">Yes, Remove</span>
                        <span wire:loading wire:target="deleteMember">Removing…</span>
                    </button>
                </div>
            </div>
        </x-card>
    </x-modal>

    <x-notifications />
    </div>
