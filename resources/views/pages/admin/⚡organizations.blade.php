<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new class extends Component
{
    use WithPagination, WithFileUploads, WireUiActions;

    public $showModal = false;
    public $editMode = false;
    public $deleteModal = false;

    // Model properties
    public $organizationId;
    public $owner_id;
    public $name;
    public $slug;
    public $logo;
    public $website;
    public $address;
    public $phone;
    public $email;
    public $logoFile;

    // Search and filters
    public $search = '';
    public $perPage = 10;
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    protected function rules()
    {
        return [
            'owner_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:organizations,slug,' . $this->organizationId,
            'logoFile' => 'nullable|image|max:2048',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ];
    }

    public function updatedName($value)
    {
        if (!$this->editMode) {
            $this->slug = Str::slug($value);
        }
    }

    public function getOrganizationsProperty()
    {
        return Organization::query()
            ->with(['owner', 'activePlan.plan'])
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%')
                    ->orWhere('slug', 'like', '%' . $this->search . '%');
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function getUsersProperty()
    {
        return User::orderBy('name')->get();
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
        $organization = Organization::findOrFail($id);

        $this->organizationId = $organization->id;
        $this->owner_id = $organization->owner_id;
        $this->name = $organization->name;
        $this->slug = $organization->slug;
        $this->logo = $organization->logo;
        $this->website = $organization->website;
        $this->address = $organization->address;
        $this->phone = $organization->phone;
        $this->email = $organization->email;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'website' => $this->website,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
        ];

        if ($this->logoFile) {
            $data['logo'] = $this->logoFile->store('organizations/logos', 'public');
        }

        if ($this->editMode) {
            $organization = Organization::findOrFail($this->organizationId);
            $organization->update($data);
            $this->notification()->success('Success', 'Organization updated successfully');
        } else {
            Organization::create($data);
            $this->notification()->success('Success', 'Organization created successfully');
        }

        $this->resetForm();
        $this->showModal = false;
    }

    public function confirmDelete($id)
    {
        $this->organizationId = $id;
        $this->deleteModal = true;
    }

    public function delete()
    {
        $organization = Organization::findOrFail($this->organizationId);
        $organization->delete();

        $this->deleteModal = false;
        $this->notification()->success('Success', 'Organization deleted successfully');
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset([
            'organizationId',
            'owner_id',
            'name',
            'slug',
            'logo',
            'website',
            'address',
            'phone',
            'email',
            'logoFile',
            'editMode',
        ]);
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
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Organizations</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">Manage all organizations and their details</p>
    </div>

    {{-- Actions Bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex-1 max-w-md">
                <x-input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search organizations..."
                    icon="magnifying-glass"
                />
            </div>

            <div class="flex items-center gap-3">
                <x-select wire:model.live="perPage" placeholder="Per page" class="w-32"
                    :options="[['value'=>10,'label'=>'10'],['value'=>25,'label'=>'25'],['value'=>50,'label'=>'50'],['value'=>100,'label'=>'100']]"
                    option-value="value" option-label="label"
                />

                <x-button
                    primary
                    icon="plus"
                    wire:click="create"
                >
                    New Organization
                </x-button>
            </div>
        </div>
    </div>

    {{-- Organizations Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left">
                            <button wire:click="sortBy('name')" class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider hover:text-gray-700 dark:hover:text-gray-200">
                                Name
                                @if($sortField === 'name')
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Owner
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Contact
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Active Plan
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
                    @forelse($this->organizations as $organization)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    @if($organization->logo)
                                        <img src="{{ Storage::url($organization->logo) }}" alt="{{ $organization->name }}" class="h-10 w-10 rounded-full object-cover mr-3">
                                    @else
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center mr-3">
                                            <span class="text-white font-semibold text-sm">{{ substr($organization->name, 0, 2) }}</span>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $organization->name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $organization->slug }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-gray-100">{{ $organization->owner->name ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 dark:text-gray-100">{{ $organization->email ?? 'N/A' }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $organization->phone ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($organization->activePlan)
                                    <x-badge positive>
                                        {{ $organization->activePlan->plan->name ?? 'N/A' }}
                                    </x-badge>
                                @else
                                    <x-badge flat>No Plan</x-badge>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $organization->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <x-button
                                        xs
                                        primary
                                        icon="pencil"
                                        wire:click="edit({{ $organization->id }})"
                                    />
                                    <x-button
                                        xs
                                        negative
                                        icon="trash"
                                        wire:click="confirmDelete({{ $organization->id }})"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <x-icon name="building-office" class="w-12 h-12 text-gray-400 mb-3" />
                                    <p class="text-gray-500 dark:text-gray-400">No organizations found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $this->organizations->links() }}
        </div>
    </div>

    {{-- Create/Edit Modal --}}
    <x-modal wire:model.defer="showModal" max-width="2xl">
        <x-card title="{{ $editMode ? 'Edit Organization' : 'Create Organization' }}" class="relative">
            <form wire:submit.prevent="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Owner --}}
                    <div class="col-span-2">
                        <x-select
                            label="Owner *"
                            placeholder="Select owner"
                            wire:model="owner_id"
                            :options="$this->users"
                            option-label="name"
                            option-value="id"
                        />
                        @error('owner_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Name --}}
                    <div>
                        <x-input
                            label="Name *"
                            placeholder="Organization name"
                            wire:model.live="name"
                        />
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Slug --}}
                    <div>
                        <x-input
                            label="Slug *"
                            placeholder="organization-slug"
                            wire:model="slug"
                        />
                        @error('slug') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <x-input
                            label="Email"
                            placeholder="contact@organization.com"
                            wire:model="email"
                            type="email"
                        />
                        @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Phone --}}
                    <div>
                        <x-input
                            label="Phone"
                            placeholder="+1234567890"
                            wire:model="phone"
                        />
                        @error('phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Website --}}
                    <div class="col-span-2">
                        <x-input
                            label="Website"
                            placeholder="https://example.com"
                            wire:model="website"
                        />
                        @error('website') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Address --}}
                    <div class="col-span-2">
                        <x-textarea
                            label="Address"
                            placeholder="Enter organization address"
                            wire:model="address"
                            rows="3"
                        />
                        @error('address') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Logo Upload --}}
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Logo</label>
                        <input type="file" wire:model="logoFile" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                        @error('logoFile') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror

                        @if ($logoFile)
                            <div class="mt-2">
                                <img src="{{ $logoFile->temporaryUrl() }}" class="h-20 w-20 object-cover rounded-lg">
                            </div>
                        @elseif($logo)
                            <div class="mt-2">
                                <img src="{{ Storage::url($logo) }}" class="h-20 w-20 object-cover rounded-lg">
                            </div>
                        @endif
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
        <x-card class="relative">
            <div class="text-center">
                <x-icon name="exclamation-circle" class="w-16 h-16 text-red-500 mx-auto mb-4" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Delete Organization</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">Are you sure you want to delete this organization? This action cannot be undone.</p>
            </div>

            <x-slot name="footer">
                <div class="flex justify-center gap-3">
                    <x-button flat label="Cancel" wire:click="$set('deleteModal', false)" />
                    <x-button negative label="Delete" wire:click="delete" spinner="delete" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>
    <x-spinner/>
</div>
