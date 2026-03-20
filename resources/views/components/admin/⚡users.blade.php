<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;

new class extends Component
{
    use WithPagination;
    public $search = '';
    public $sortField = 'name';
    public $sortDirection = 'asc';
    public $showModal = false;
    public $isEditMode = false;
    public $userId = null;

    // Form fields
    public $name = '';
    public $email = '';
    public $phone = '';
    public $password = '';
    public $role = 'member';

    protected $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email',
        'phone' => 'required|string|max:20',
        'password' => 'required|min:8|confirmed',
        'role' => 'required|in:member,admin',
    ];

    protected $queryString = ['search', 'sortField', 'sortDirection'];

    public function mount()
    {
        // For unique rule in edit mode
        $this->rules['email'] = 'required|email|max:255|unique:users,email,' . $this->userId;
    }

    public function updated($propertyName)
    {
        if ($this->isEditMode) {
            $this->rules['email'] = 'required|email|max:255|unique:users,email,' . $this->userId;
        }

        $this->validateOnly($propertyName);
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
        $this->isEditMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $this->userId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        $this->role = $user->role;
        $this->password = ''; // Don't show current password
        $this->isEditMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        $validatedData = $this->validate();

        if ($this->isEditMode) {
            $user = User::findOrFail($this->userId);
            $updateData = [
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'],
                'role' => $validatedData['role'],
            ];

            if (!empty($validatedData['password'])) {
                $updateData['password'] = bcrypt($validatedData['password']);
            }

            $user->update($updateData);

            $this->notification()->success(
                $title = 'Success!',
                $description = 'User updated successfully.'
            );
        } else {
            User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'],
                'password' => bcrypt($validatedData['password']),
                'role' => $validatedData['role'],
            ]);

            $this->notification()->success(
                $title = 'Success!',
                $description = 'User created successfully.'
            );
        }

        $this->closeModal();
        $this->resetPage();
    }

    public function delete($id)
    {
        $this->dialog()->confirm([
            'title'       => 'Are you sure?',
            'description' => 'This action cannot be undone.',
            'icon'        => 'error',
            'accept'      => [
                'label'  => 'Yes, delete it',
                'method' => 'confirmDelete',
                'params' => $id,
            ],
            'reject' => [
                'label'  => 'Cancel',
            ],
        ]);
    }

    public function confirmDelete($id)
    {
        try {
            $user = User::findOrFail($id);

            // Check if user has related records
            if ($user->payments()->exists() || $user->pledges()->exists()) {
                $this->notification()->error(
                    $title = 'Cannot Delete',
                    $description = 'User has related payments or pledges. Please remove them first.'
                );
                return;
            }

            $user->delete();

            $this->notification()->success(
                $title = 'Deleted!',
                $description = 'User has been deleted.'
            );

            $this->resetPage();
        } catch (\Exception $e) {
            $this->notification()->error(
                $title = 'Error!',
                $description = 'Unable to delete user.'
            );
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset(['userId', 'name', 'email', 'phone', 'password', 'role', 'isEditMode']);
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function getUsersProperty()
    {
        return User::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('phone', 'like', '%' . $this->search . '%')
                      ->orWhere('role', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }
};
?>

<div>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
            <!-- Header -->
            <div class="md:flex md:items-center md:justify-between mb-6">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                        User Management
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Manage your system users and their permissions
                    </p>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <x-button
                        primary
                        wire:click="create"
                        icon="plus"
                        label="Add New User"
                    />
                </div>
            </div>

            <!-- Search and Stats -->
            <div class="mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input
                            wire:model.debounce.300ms="search"
                            icon="search"
                            placeholder="Search users..."
                            class="w-full"
                        />
                    </div>
                    <div class="flex items-center justify-end space-x-4">
                        <x-badge flat primary>
                            Total Users: {{ $users->total() }}
                        </x-badge>
                        <x-badge flat positive>
                            Admins: {{ \App\Models\User::where('role', 'admin')->count() }}
                        </x-badge>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="sortBy('name')">
                                    <div class="flex items-center">
                                        Name
                                        @if($sortField === 'name')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="sortBy('email')">
                                    <div class="flex items-center">
                                        Email
                                        @if($sortField === 'email')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Phone
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="sortBy('role')">
                                    <div class="flex items-center">
                                        Role
                                        @if($sortField === 'role')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Created
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($users as $user)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                <span class="text-blue-600 font-semibold">
                                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                                </span>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $user->name }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $user->email }}</div>
                                        @if($user->email_verified_at)
                                            <x-badge flat positive xs class="mt-1">Verified</x-badge>
                                        @else
                                            <x-badge flat warning xs class="mt-1">Not Verified</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $user->phone }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <x-badge :color="$user->role === 'admin' ? 'negative' : 'primary'" flat>
                                            {{ ucfirst($user->role) }}
                                        </x-badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $user->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <x-button.circle
                                                slate
                                                icon="pencil"
                                                wire:click="edit({{ $user->id }})"
                                                title="Edit"
                                            />
                                            <x-button.circle
                                                negative
                                                icon="trash"
                                                wire:click="delete({{ $user->id }})"
                                                title="Delete"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center">
                                        <div class="text-gray-400">
                                            <x-icon name="user-group" class="w-12 h-12 mx-auto text-gray-300" />
                                            <p class="mt-2 text-sm font-medium text-gray-900">No users found</p>
                                            <p class="mt-1 text-sm text-gray-500">
                                                {{ $search ? 'Try a different search term' : 'Get started by creating a new user' }}
                                            </p>
                                            @if(!$search)
                                                <div class="mt-4">
                                                    <x-button primary wire:click="create">
                                                        <x-icon name="plus" class="w-4 h-4 mr-2" />
                                                        Add User
                                                    </x-button>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($users->hasPages())
                    <div class="border-t border-gray-200 px-4 py-3">
                        {{ $users->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <x-modal wire:model="showModal">
        <x-card title="{{ $isEditMode ? 'Edit User' : 'Create New User' }}">
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input
                        label="Full Name"
                        wire:model.defer="name"
                        placeholder="Enter full name"
                        :error="$errors->first('name')"
                    />

                    <x-input
                        label="Email Address"
                        type="email"
                        wire:model.defer="email"
                        placeholder="user@example.com"
                        :error="$errors->first('email')"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input
                        label="Phone Number"
                        wire:model.defer="phone"
                        placeholder="+1 (555) 123-4567"
                        :error="$errors->first('phone')"
                    />

                    <x-select
                        label="Role"
                        :options="[
                            ['label' => 'Member', 'value' => 'member'],
                            ['label' => 'Admin', 'value' => 'admin']
                        ]"
                        wire:model.defer="role"
                        :error="$errors->first('role')"
                    />
                </div>

                @if(!$isEditMode)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-inputs.password
                            label="Password"
                            wire:model.defer="password"
                            placeholder="Minimum 8 characters"
                            :error="$errors->first('password')"
                        />

                        <x-inputs.password
                            label="Confirm Password"
                            wire:model.defer="password_confirmation"
                            placeholder="Confirm password"
                        />
                    </div>
                @else
                    <x-alert info>
                        <span class="font-medium">Password Update:</span>
                        Leave password fields empty to keep current password
                    </x-alert>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-inputs.password
                            label="New Password (Optional)"
                            wire:model.defer="password"
                            placeholder="Leave empty to keep current"
                        />

                        <x-inputs.password
                            label="Confirm Password"
                            wire:model.defer="password_confirmation"
                            placeholder="Confirm new password"
                        />
                    </div>
                @endif
            </div>

            <x-slot name="footer">
                <div class="flex justify-between">
                    <x-button flat label="Cancel" wire:click="closeModal" />
                    <div class="flex space-x-2">
                        @if($isEditMode)
                            <x-button secondary icon="clock" label="Reset Password" />
                        @endif
                        <x-button
                            primary
                            :label="$isEditMode ? 'Update User' : 'Create User'"
                            wire:click="save"
                            spinner
                        />
                    </div>
                </div>
            </x-slot>
        </x-card>
    </x-modal>
</div>
