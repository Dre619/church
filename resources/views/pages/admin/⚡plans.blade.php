<?php

use App\Models\Plan;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new class extends Component
{
    use WithPagination, WireUiActions;

    public $showModal = false;
    public $editMode = false;
    public $deleteModal = false;

    // Model properties
    public $planId;
    public $name;
    public $slug;
    public $description;
    public $price;
    public $max_members;
    public $is_active        = true;
    public $is_trial         = false;
    public $trial_days;
    public $can_view_reports = true;
    public $can_export       = true;

    // Search and filters
    public $search = '';
    public $perPage = 10;
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    public $filterActive = 'all';

    protected function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'slug'        => 'required|string|max:255|unique:plans,slug,' . $this->planId,
            'description' => 'nullable|string|max:500',
            'price'       => 'required|numeric|min:0',
            'max_members' => 'nullable|integer|min:1',
            'is_active'        => 'boolean',
            'is_trial'         => 'boolean',
            'trial_days'       => 'nullable|integer|min:1|required_if:is_trial,true',
            'can_view_reports' => 'boolean',
            'can_export'       => 'boolean',
        ];
    }

    public function updatedName($value)
    {
        if (!$this->editMode) {
            $this->slug = Str::slug($value);
        }
    }

    public function getPlansProperty()
    {
        return Plan::query()
            ->withCount('activeOrganizationPlans')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('slug', 'like', '%' . $this->search . '%');
            })
            ->when($this->filterActive !== 'all', function ($query) {
                $query->where('is_active', $this->filterActive === 'active');
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $plan = Plan::findOrFail($id);

        $this->planId           = $plan->id;
        $this->name             = $plan->name;
        $this->slug             = $plan->slug;
        $this->description      = $plan->description;
        $this->price            = $plan->price;
        $this->max_members      = $plan->max_members;
        $this->is_active        = $plan->is_active;
        $this->is_trial         = $plan->is_trial;
        $this->trial_days       = $plan->trial_days;
        $this->can_view_reports = $plan->can_view_reports;
        $this->can_export       = $plan->can_export;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'price'       => $this->price,
            'max_members' => $this->max_members,
            'is_active'        => $this->is_active,
            'is_trial'         => $this->is_trial,
            'trial_days'       => $this->is_trial ? $this->trial_days : null,
            'can_view_reports' => $this->can_view_reports,
            'can_export'       => $this->can_export,
        ];

        if ($this->editMode) {
            $plan = Plan::findOrFail($this->planId);
            $plan->update($data);
            $this->notification()->success('Success', 'Plan updated successfully');
        } else {
            Plan::create($data);
            $this->notification()->success('Success', 'Plan created successfully');
        }

        $this->resetForm();
        $this->showModal = false;
    }

    public function toggleStatus($id)
    {
        $plan = Plan::findOrFail($id);
        $plan->update(['is_active' => !$plan->is_active]);

        $this->notification()->success(
            'Success',
            'Plan ' . ($plan->is_active ? 'activated' : 'deactivated') . ' successfully'
        );
    }

    public function confirmDelete($id)
    {
        $this->planId = $id;
        $this->deleteModal = true;
    }

    public function delete()
    {
        $plan = Plan::findOrFail($this->planId);

        if ($plan->activeOrganizationPlans()->count() > 0) {
            $this->notification()->error('Error', 'Cannot delete plan with active subscriptions');
            $this->deleteModal = false;
            return;
        }

        $plan->delete();

        $this->deleteModal = false;
        $this->notification()->success('Success', 'Plan deleted successfully');
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset([
            'planId',
            'name',
            'slug',
            'description',
            'price',
            'max_members',
            'is_active',
            'is_trial',
            'trial_days',
            'can_view_reports',
            'can_export',
            'editMode',
        ]);
        $this->is_active        = true;
        $this->can_view_reports = true;
        $this->can_export       = true;
        $this->resetValidation();
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }
};
?>

<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Subscription Plans</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">Manage subscription plans and pricing</p>
    </div>

    {{-- Actions Bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex-1 max-w-md">
                <x-input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search plans..."
                    icon="magnifying-glass"
                />
            </div>

            <div class="flex items-center gap-3">
                <x-select
                    wire:model.live="filterActive"
                    placeholder="Filter by status"
                    class="w-40"
                >
                    <x-select.option label="All Plans" value="all" />
                    <x-select.option label="Active" value="active" />
                    <x-select.option label="Inactive" value="inactive" />
                </x-select>

                <x-select
                    wire:model.live="perPage"
                    placeholder="Per page"
                    class="w-32"
                >
                    <x-select.option label="10" value="10" />
                    <x-select.option label="25" value="25" />
                    <x-select.option label="50" value="50" />
                    <x-select.option label="100" value="100" />
                </x-select>

                <x-button
                    primary
                    icon="plus"
                    wire:click="create"
                >
                    New Plan
                </x-button>
            </div>
        </div>
    </div>

    {{-- Plans Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        @forelse($this->plans as $plan)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden border-2 {{ $plan->is_active ? 'border-primary-500' : 'border-gray-200 dark:border-gray-700' }} transition-all hover:shadow-md">
                <div class="p-6">
                    {{-- Plan Header --}}
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $plan->name }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $plan->slug }}</p>
                        </div>
                        @if($plan->is_active)
                            <x-badge positive>Active</x-badge>
                        @else
                            <x-badge flat>Inactive</x-badge>
                        @endif
                    </div>

                    {{-- Trial badge --}}
                    @if($plan->is_trial)
                        <div class="mb-3">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-200">
                                <x-icon name="clock" class="w-3.5 h-3.5" />
                                {{ $plan->trial_days }}-Day Free Trial
                            </span>
                        </div>
                    @endif

                    {{-- Description --}}
                    @if($plan->description)
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ $plan->description }}</p>
                    @endif

                    {{-- Price --}}
                    <div class="mb-6">
                        <div class="flex items-baseline">
                            @if($plan->is_trial)
                                <span class="text-4xl font-bold text-amber-600">Free</span>
                            @else
                                <span class="text-4xl font-bold text-gray-900 dark:text-gray-100">K{{ number_format($plan->price, 2) }}</span>
                                <span class="text-gray-500 dark:text-gray-400 ml-2">/month</span>
                            @endif
                        </div>
                    </div>

                    {{-- Features --}}
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center text-sm">
                            <x-icon name="users" class="w-5 h-5 text-primary-500 mr-2" />
                            <span class="text-gray-700 dark:text-gray-300">
                                @if($plan->max_members)
                                    Up to <strong>{{ number_format($plan->max_members) }}</strong> members
                                @else
                                    <strong>Unlimited</strong> members
                                @endif
                            </span>
                        </div>
                        <div class="flex items-center text-sm">
                            <x-icon name="building-office" class="w-5 h-5 text-primary-500 mr-2" />
                            <span class="text-gray-700 dark:text-gray-300"><strong>{{ $plan->active_organization_plans_count }}</strong> active subscriptions</span>
                        </div>
                        <div class="flex items-center gap-2 mt-3 flex-wrap">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $plan->can_view_reports ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' }}">
                                <x-icon name="{{ $plan->can_view_reports ? 'check' : 'x-mark' }}" class="w-3 h-3" /> Reports
                            </span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $plan->can_export ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' }}">
                                <x-icon name="{{ $plan->can_export ? 'check' : 'x-mark' }}" class="w-3 h-3" /> Excel Export
                            </span>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <x-button
                            xs
                            flat
                            icon="pencil"
                            wire:click="edit({{ $plan->id }})"
                            class="flex-1"
                        >
                            Edit
                        </x-button>
                        @if($plan->is_active)
                            <x-button
                                xs
                                warning
                                icon="swatch"
                                wire:click="toggleStatus({{ $plan->id }})"
                                class="flex-1"
                            >
                                Deactivate
                            </x-button>
                        @else
                            <x-button
                                xs
                                positive
                                icon="swatch"
                                wire:click="toggleStatus({{ $plan->id }})"
                                class="flex-1"
                            >
                                Activate
                            </x-button>
                        @endif
                        <x-button
                            xs
                            negative
                            icon="trash"
                            wire:click="confirmDelete({{ $plan->id }})"
                        />
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-12 text-center">
                    <x-icon name="clipboard" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                    <p class="text-gray-500 dark:text-gray-400">No plans found</p>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm px-6 py-4">
        {{ $this->plans->links() }}
    </div>

    {{-- Create/Edit Modal --}}
    <x-modal wire:model.defer="showModal" max-width="2xl">
        <x-card class="relative" title="{{ $editMode ? 'Edit Plan' : 'Create Plan' }}">
            <form wire:submit.prevent="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Name --}}
                    <div>
                        <x-input
                            label="Plan Name *"
                            placeholder="e.g., Standard"
                            wire:model.live="name"
                        />
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Slug --}}
                    <div>
                        <x-input
                            label="Slug *"
                            placeholder="standard"
                            wire:model="slug"
                        />
                        @error('slug') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Description --}}
                    <div class="md:col-span-2">
                        <x-input
                            label="Description"
                            placeholder="Short description shown on the pricing page"
                            wire:model="description"
                        />
                        @error('description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Price --}}
                    <div>
                        <x-currency
                            label="Price (ZMW / month) *"
                            placeholder="0.00"
                            wire:model="price"
                            prefix="K"
                            thousands=","
                            decimal="."
                            precision="2"
                        />
                        @error('price') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Max Members --}}
                    <div>
                        <x-number
                            label="Max Members (leave blank for unlimited)"
                            placeholder="e.g., 500"
                            wire:model="max_members"
                            min="1"
                        />
                        @error('max_members') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Is Trial --}}
                    <div class="flex items-center gap-6 pt-4">
                        <x-toggle label="Free Trial Plan" wire:model.live="is_trial" />
                        <x-toggle label="Active" wire:model="is_active" lg />
                    </div>

                    {{-- Trial Days (only when is_trial) --}}
                    @if($is_trial)
                    <div>
                        <x-number
                            label="Trial Duration (days) *"
                            placeholder="3"
                            wire:model="trial_days"
                            min="1"
                        />
                        @error('trial_days') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    @endif

                    {{-- Feature flags --}}
                    <div class="md:col-span-2 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">Feature Access</p>
                        <div class="flex flex-wrap gap-6">
                            <x-toggle label="Can view reports" wire:model="can_view_reports" />
                            <x-toggle label="Can export to Excel" wire:model="can_export" />
                        </div>
                    </div>
                </div>

                <x-slot name="footer">
                    <div class="flex justify-end gap-3">
                        <x-button flat label="Cancel" wire:click="closeModal" />
                        <x-button primary label="{{ $editMode ? 'Update' : 'Create' }}" type="submit" wire:click="save" />
                    </div>
                </x-slot>
            </form>
        </x-card>
    </x-modal>

    {{-- Delete Confirmation Modal --}}
    <x-modal wire:model.defer="deleteModal" max-width="md">
        <x-card>
            <div class="text-center">
                <x-icon name="exclamation-circle" class="w-16 h-16 text-red-500 mx-auto mb-4" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Delete Plan</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">Are you sure you want to delete this plan? This action cannot be undone and will fail if there are active subscriptions.</p>
            </div>

            <x-slot name="footer">
                <div class="flex justify-center gap-3">
                    <x-button flat label="Cancel" wire:click="$set('deleteModal', false)" />
                    <x-button negative label="Delete" wire:click="delete" spinner="delete" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>
</div>
