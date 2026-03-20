<?php

use App\Models\Organization;
use App\Models\OrganizationPlan;
use App\Models\Plan;
use Carbon\Carbon;
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
    public $organizationPlanId;
    public $organization_id;
    public $plan_id;
    public $start_date;
    public $end_date;
    public $is_active = true;

    // Search and filters
    public $search = '';
    public $perPage = 10;
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    public $filterActive = 'all';
    public $filterOrganization = '';
    public $filterPlan = '';

    protected function rules()
    {
        return [
            'organization_id' => 'required|exists:organizations,id',
            'plan_id' => 'required|exists:plans,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'boolean',
        ];
    }

    public function mount()
    {
        $this->start_date = now()->format('Y-m-d');
    }

    public function getOrganizationsProperty()
    {
        return Organization::orderBy('name')->get();
    }

    public function getPlansProperty()
    {
        return Plan::where('is_active', true)->orderBy('name')->get();
    }

    public function getOrganizationPlansProperty()
    {
        return OrganizationPlan::query()
            ->with(['organization', 'plan'])
            ->when($this->search, function ($query) {
                $query->whereHas('organization', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                })->orWhereHas('plan', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterActive !== 'all', function ($query) {
                $query->where('is_active', $this->filterActive === 'active');
            })
            ->when($this->filterOrganization, function ($query) {
                $query->where('organization_id', $this->filterOrganization);
            })
            ->when($this->filterPlan, function ($query) {
                $query->where('plan_id', $this->filterPlan);
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
        $organizationPlan = OrganizationPlan::findOrFail($id);

        $this->organizationPlanId = $organizationPlan->id;
        $this->organization_id = $organizationPlan->organization_id;
        $this->plan_id = $organizationPlan->plan_id;
        $this->start_date = $organizationPlan->start_date ? Carbon::parse($organizationPlan->start_date)->format('Y-m-d') : null;
        $this->end_date = $organizationPlan->end_date ? Carbon::parse($organizationPlan->end_date)->format('Y-m-d') : null;
        $this->is_active = $organizationPlan->is_active;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'organization_id' => $this->organization_id,
            'plan_id' => $this->plan_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_active' => $this->is_active,
        ];

        if ($this->editMode) {
            $organizationPlan = OrganizationPlan::findOrFail($this->organizationPlanId);
            $organizationPlan->update($data);
            $this->notification()->success('Success', 'Organization plan updated successfully');
        } else {
            // Deactivate other plans for this organization if this is active
            if ($this->is_active) {
                OrganizationPlan::where('organization_id', $this->organization_id)
                    ->update(['is_active' => false]);
            }

            OrganizationPlan::create($data);
            $this->notification()->success('Success', 'Organization plan created successfully');
        }

        $this->resetForm();
        $this->showModal = false;
    }

    public function toggleStatus($id)
    {
        $organizationPlan = OrganizationPlan::findOrFail($id);

        // If activating, deactivate other plans for this organization
        if (!$organizationPlan->is_active) {
            OrganizationPlan::where('organization_id', $organizationPlan->organization_id)
                ->where('id', '!=', $id)
                ->update(['is_active' => false]);
        }

        $organizationPlan->update(['is_active' => !$organizationPlan->is_active]);

        $this->notification()->success(
            'Success',
            'Plan ' . ($organizationPlan->is_active ? 'activated' : 'deactivated') . ' successfully'
        );
    }

    public function confirmDelete($id)
    {
        $this->organizationPlanId = $id;
        $this->deleteModal = true;
    }

    public function delete()
    {
        $organizationPlan = OrganizationPlan::findOrFail($this->organizationPlanId);
        $organizationPlan->delete();

        $this->deleteModal = false;
        $this->notification()->success('Success', 'Organization plan deleted successfully');
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset([
            'organizationPlanId',
            'organization_id',
            'plan_id',
            'end_date',
            'is_active',
            'editMode',
        ]);
        $this->start_date = now()->format('Y-m-d');
        $this->is_active = true;
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
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Organization Plans</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">Manage organization subscriptions and plan assignments</p>
    </div>

    {{-- Actions Bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex-1 max-w-md">
                    <x-input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search organizations or plans..."
                        icon="magnifying-glass"
                    />
                </div>

                <div class="flex items-center gap-3">
                    <x-button
                        primary
                        icon="plus"
                        wire:click="create"
                    >
                        Assign Plan
                    </x-button>
                </div>
            </div>

            {{-- Filters Row --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <x-select
                    wire:model.live="filterActive"
                    placeholder="Filter by status"
                >
                    <x-select.option label="All Status" value="all" />
                    <x-select.option label="Active" value="active" />
                    <x-select.option label="Inactive" value="inactive" />
                </x-select>

                <x-select
                    wire:model.live="filterOrganization"
                    placeholder="Filter by organization"
                    :options="$this->organizations"
                    option-label="name"
                    option-value="id"
                />

                <x-select
                    wire:model.live="filterPlan"
                    placeholder="Filter by plan"
                    :options="$this->plans"
                    option-label="name"
                    option-value="id"
                />

                <x-select
                    wire:model.live="perPage"
                    placeholder="Per page"
                >
                    <x-select.option label="10" value="10" />
                    <x-select.option label="25" value="25" />
                    <x-select.option label="50" value="50" />
                    <x-select.option label="100" value="100" />
                </x-select>
            </div>
        </div>
    </div>

    {{-- Organization Plans Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Organization
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Plan
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Start Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            End Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left">
                            <button wire:click="sortBy('created_at')" class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider hover:text-gray-700 dark:hover:text-gray-200">
                                Created
                                @if($sortField === 'created_at')
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($this->organizationPlans as $orgPlan)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    @if($orgPlan->organization->logo)
                                        <img src="{{ Storage::url($orgPlan->organization->logo) }}" alt="{{ $orgPlan->organization->name }}" class="h-10 w-10 rounded-full object-cover mr-3">
                                    @else
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-500 flex items-center justify-center mr-3">
                                            <span class="text-white font-semibold text-sm">{{ substr($orgPlan->organization->name, 0, 2) }}</span>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $orgPlan->organization->name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $orgPlan->organization->email }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $orgPlan->plan->name }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">${{ number_format($orgPlan->plan->price, 2) }}/month</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ $orgPlan->start_date ? \Carbon\Carbon::parse($orgPlan->start_date)->format('M d, Y') : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ $orgPlan->end_date ? \Carbon\Carbon::parse($orgPlan->end_date)->format('M d, Y') : 'No end date' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col gap-1">
                                    <x-badge :positive="$orgPlan->is_active" :flat="!$orgPlan->is_active">
                                        {{ $orgPlan->is_active ? 'Active' : 'Inactive' }}
                                    </x-badge>
                                    @if($orgPlan->hasActivePlan())
                                        <x-badge positive class="text-xs">Valid</x-badge>
                                    @elseif($orgPlan->end_date && \Carbon\Carbon::parse($orgPlan->end_date)->isPast())
                                        <x-badge warning class="text-xs">Expired</x-badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $orgPlan->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <x-button
                                        xs
                                        {{ $orgPlan->is_active ? 'warning' : 'positive' }}
                                        icon="switch-horizontal"
                                        wire:click="toggleStatus({{ $orgPlan->id }})"
                                    />
                                    <x-button
                                        xs
                                        primary
                                        icon="pencil"
                                        wire:click="edit({{ $orgPlan->id }})"
                                    />
                                    <x-button
                                        xs
                                        negative
                                        icon="trash"
                                        wire:click="confirmDelete({{ $orgPlan->id }})"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <x-icon name="clipboard" class="w-12 h-12 text-gray-400 mb-3" />
                                    <p class="text-gray-500 dark:text-gray-400">No organization plans found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $this->organizationPlans->links() }}
        </div>
    </div>

    {{-- Create/Edit Modal --}}
    <x-modal wire:model.defer="showModal" max-width="2xl">
        <x-card class="relative" title="{{ $editMode ? 'Edit Organization Plan' : 'Assign Plan to Organization' }}">
            <form wire:submit.prevent="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Organization --}}
                    <div class="col-span-2">
                        <x-select
                            label="Organization *"
                            placeholder="Select organization"
                            wire:model="organization_id"
                            :options="$this->organizations"
                            option-label="name"
                            option-value="id"
                        />
                        @error('organization_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Plan --}}
                    <div class="col-span-2">
                        <x-select
                            label="Plan *"
                            placeholder="Select plan"
                            wire:model="plan_id"
                            :options="$this->plans"
                            option-label="name"
                            option-value="id"
                        />
                        @error('plan_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Start Date --}}
                    <div>
                        <x-datetime-picker
                            label="Start Date *"
                            placeholder="Select start date"
                            wire:model="start_date"
                            without-time
                        />
                        @error('start_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- End Date --}}
                    <div>
                        <x-datetime-picker
                            label="End Date"
                            placeholder="Select end date (optional)"
                            wire:model="end_date"
                            without-time
                        />
                        @error('end_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Is Active --}}
                    <div class="col-span-2 flex items-center">
                        <x-toggle
                            label="Active (Only one plan can be active per organization)"
                            wire:model="is_active"
                            lg
                        />
                    </div>
                </div>

                <x-slot name="footer">
                    <div class="flex justify-end gap-3">
                        <x-button flat label="Cancel" wire:click="closeModal" />
                        <x-button primary label="{{ $editMode ? 'Update' : 'Assign' }}" type="submit" wire:click="save" />
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
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Delete Organization Plan</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">Are you sure you want to delete this organization plan assignment? This action cannot be undone.</p>
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
