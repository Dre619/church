<?php

use App\Models\User;
use App\Models\OrganizationUser;
use App\Imports\MembersImport;
use App\Exports\MembersTemplateExport;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use WireUi\Traits\WireUiActions;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component
{
    use WithPagination, WithFileUploads, WireUiActions;

    // ── Modals ────────────────────────────────────────────────────────────────
    public bool $modalOpen         = false;
    public bool $confirmDeleteOpen = false;
    public bool $importModalOpen   = false;

    // ── Create / Edit form ───────────────────────────────────────────────────
    public ?int   $editingUserId         = null;
    public string $name                  = '';
    public string $email                 = '';
    public string $password              = '';
    public string $password_confirmation = '';

    // ── Delete ───────────────────────────────────────────────────────────────
    public ?int $deletingUserId = null;

    // ── Search ───────────────────────────────────────────────────────────────
    public string $search = '';

    // ── Import ───────────────────────────────────────────────────────────────
    public $importFile    = null;
    public array $importErrors  = [];
    public int   $importedCount = 0;
    public bool  $importDone    = false;

    // ─────────────────────────────────────────────────────────────────────────

    protected function rules(): array
    {
        $passwordRules = $this->editingUserId
            ? 'nullable|min:8|confirmed'
            : 'required|min:8|confirmed';

        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email' . ($this->editingUserId ? ",{$this->editingUserId}" : ''),
            'password' => $passwordRules,
        ];
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getMembersProperty()
    {
        $organization_id = auth()->user()->myOrganization->organization_id;

        return User::whereHas('myOrganization', function ($q) use ($organization_id) {
                $q->where('organization_id', $organization_id)
                  ->where('user_type', 'member');
            })
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%")
                )
            )
            ->latest()
            ->paginate(10);
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    // ── Create / Edit ─────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        $this->modalOpen = true;
    }

    public function openEdit(int $userId): void
    {
        $this->resetForm();
        $user                = User::findOrFail($userId);
        $this->editingUserId = $user->id;
        $this->name          = $user->name;
        $this->email         = $user->email;
        $this->modalOpen     = true;
    }

    public function save(): void
    {
        $this->validate();

        $organization_id = auth()->user()->myOrganization->organization_id;

        if ($this->editingUserId) {
            $user        = User::findOrFail($this->editingUserId);
            $user->name  = $this->name;
            $user->email = $this->email;
            if ($this->password) {
                $user->password = bcrypt($this->password);
            }
            $user->save();
            $this->notification()->success('Member updated', "{$user->name} has been updated successfully.");
        } else {
            $user = User::create([
                'name'     => $this->name,
                'email'    => $this->email,
                'password' => bcrypt($this->password),
                'role'     => 'member',
            ]);

            OrganizationUser::create([
                'user_id'         => $user->id,
                'organization_id' => $organization_id,
                'user_type'       => 'member',
            ]);

            $this->notification()->success('Member added', "{$user->name} has been added to your organization.");
        }

        $this->modalOpen = false;
        $this->resetForm();
        $this->resetPage();
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function confirmDelete(int $userId): void
    {
        $this->deletingUserId    = $userId;
        $this->confirmDeleteOpen = true;
    }

    public function delete(): void
    {
        if (! $this->deletingUserId) return;

        $organization_id = auth()->user()->myOrganization->organization_id;
        $user            = User::findOrFail($this->deletingUserId);

        OrganizationUser::where('user_id', $this->deletingUserId)
            ->where('organization_id', $organization_id)
            ->where('user_type', 'member')
            ->delete();

        $this->notification()->success('Member removed', "{$user->name} has been removed.");
        $this->confirmDeleteOpen = false;
        $this->deletingUserId    = null;
        $this->resetPage();
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function openImport(): void
    {
        $this->importFile    = null;
        $this->importErrors  = [];
        $this->importedCount = 0;
        $this->importDone    = false;
        $this->importModalOpen = true;
    }

    public function downloadTemplate()
    {
        return Excel::download(new MembersTemplateExport, 'members_import_template.xlsx');
    }

    public function processImport(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        $organization_id = auth()->user()->myOrganization->organization_id;

        $import = new MembersImport($organization_id);

        Excel::import($import, $this->importFile->getRealPath());

        // Collect validation failures from SkipsFailures trait
        $this->importErrors = collect($import->failures())
            ->map(fn ($failure) => [
                'row'    => $failure->row(),
                'email'  => $failure->values()['email_address'] ?? '—',
                'errors' => $failure->errors(),
            ])
            ->toArray();

        $this->importedCount = $import->importedCount;
        $this->importDone    = true;
        $this->importFile    = null;
        $this->resetPage();

        if ($this->importedCount > 0) {
            $this->notification()->success(
                'Import complete',
                "{$this->importedCount} member(s) imported."
                . (count($this->importErrors) > 0 ? ' Some rows were skipped — see details below.' : '')
            );
        } elseif (empty($this->importErrors)) {
            $this->notification()->warning('Nothing imported', 'The file appears to be empty.');
        } else {
            $this->notification()->error('Import failed', 'All rows had validation errors. Please check the details below.');
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->editingUserId         = null;
        $this->name                  = '';
        $this->email                 = '';
        $this->password              = '';
        $this->password_confirmation = '';
        $this->resetErrorBag();
    }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    {{-- ── Page Header ──────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Members</h1>
            <p class="mt-1 text-sm text-gray-500">Manage your organization's members</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-button
                wire:click="downloadTemplate"
                label="Template"
                icon="arrow-down-tray"
                flat
                secondary
            />
            <x-button wire:click="openImport"  label="Import Excel" icon="table-cells" secondary />
            <x-button wire:click="openCreate"  label="Add Member"   icon="plus"        primary />
        </div>
    </div>

    {{-- ── Search ───────────────────────────────────────────────────────────── --}}
    <div class="mb-4">
        <x-input
            wire:model.live.debounce.300ms="search"
            placeholder="Search by name or email…"
            icon="magnifying-glass"
            class="max-w-sm"
        />
    </div>

    {{-- ── Table ────────────────────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Member</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Joined</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($this->members as $member)
                    <tr wire:key="member-{{ $member->id }}" class="transition hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700">
                                    {{ $member->initials() }}
                                </div>
                                <span class="font-medium text-gray-900">{{ $member->name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ $member->email }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $member->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <x-mini-button wire:click="openEdit({{ $member->id }})"      icon="pencil" flat secondary sm />
                                <x-mini-button wire:click="confirmDelete({{ $member->id }})" icon="trash"  flat negative  sm />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <x-icon name="users" class="h-10 w-10 opacity-40" />
                                <p class="text-sm font-medium">No members found</p>
                                @if ($search)
                                    <p class="text-xs">Try a different search term</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($this->members->hasPages())
            <div class="border-t border-gray-100 px-6 py-4">
                {{ $this->members->links() }}
            </div>
        @endif
    </div>

    {{-- ── Create / Edit Modal ─────────────────────────────────────────────── --}}
    <x-modal wire:model="modalOpen" min-width="lg">
        <x-card :title="$editingUserId ? 'Edit Member' : 'Add New Member'" class="relative">
            <div class="space-y-4">
                <x-input wire:model="name"  label="Full Name"     placeholder="John Doe"         icon="user"     />
                <x-input wire:model="email" label="Email Address" placeholder="john@example.com" icon="envelope" type="email" />
                <x-input wire:model="password"
                    label="{{ $editingUserId ? 'New Password' : 'Password' }}"
                    placeholder="{{ $editingUserId ? 'Leave blank to keep current' : 'Min. 8 characters' }}"
                    icon="lock-closed" type="password" />
                <x-input wire:model="password_confirmation" label="Confirm Password" placeholder="Repeat password" icon="lock-closed" type="password" />
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('modalOpen', false)" label="Cancel" flat />
                    <x-button wire:click="save" wire:loading.attr="disabled"
                        label="{{ $editingUserId ? 'Update Member' : 'Add Member' }}"
                        primary spinner="save" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

    {{-- ── Confirm Delete ───────────────────────────────────────────────────── --}}
    <x-modal wire:model="confirmDeleteOpen" max-width="sm">
        <x-card title="Remove Member" class="relative">
            <p class="text-sm text-gray-600">Are you sure you want to remove this member? This cannot be undone.</p>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('confirmDeleteOpen', false)" label="Cancel" flat />
                    <x-button wire:click="delete" wire:loading.attr="disabled" label="Remove Member" negative spinner="delete" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

    {{-- ── Import Modal ─────────────────────────────────────────────────────── --}}
    <x-modal wire:model="importModalOpen" max-width="2xl">
        <x-card title="Import Members from Excel" class="relative">

            @if (! $importDone)

                <div class="space-y-4">
                    <p class="text-sm text-gray-600">
                        Upload an <span class="font-medium">.xlsx</span> file with columns:
                        <span class="font-mono text-xs bg-gray-100 px-1 py-0.5 rounded">Full Name</span>,
                        <span class="font-mono text-xs bg-gray-100 px-1 py-0.5 rounded">Email Address</span>,
                        <span class="font-mono text-xs bg-gray-100 px-1 py-0.5 rounded">Password</span>.
                        Use the <button wire:click="downloadTemplate" class="font-medium text-primary-600 underline hover:text-primary-800">template</button>
                        to get started.
                    </p>

                    {{-- Drop zone --}}
                    <label for="import-file"
                        class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 py-10 transition hover:border-primary-400 hover:bg-primary-50">
                        <x-icon name="arrow-up-tray" class="h-8 w-8 text-gray-400" />
                        <div class="text-center">
                            <span class="text-sm font-medium text-primary-600">Click to upload</span>
                            <span class="text-sm text-gray-500"> or drag and drop</span>
                            <p class="mt-1 text-xs text-gray-400">.xlsx or .xls · Max 5 MB</p>
                        </div>
                        <input
                            id="import-file"
                            type="file"
                            wire:model="importFile"
                            accept=".xlsx,.xls"
                            class="sr-only"
                        />
                    </label>

                    {{-- File selected --}}
                    @if ($importFile)
                        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                            <x-icon name="document-check" class="h-4 w-4 flex-shrink-0" />
                            <span class="font-medium">{{ $importFile->getClientOriginalName() }}</span>
                            <span class="ml-auto text-green-500">{{ round($importFile->getSize() / 1024, 1) }} KB</span>
                        </div>
                    @endif

                    @error('importFile')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

            @else

                {{-- Results ──────────────────────────────────────── --}}
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-center">
                            <p class="text-3xl font-bold text-green-700">{{ $importedCount }}</p>
                            <p class="mt-1 text-xs font-medium text-green-600">Imported successfully</p>
                        </div>
                        <div class="rounded-xl border {{ count($importErrors) > 0 ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-gray-50' }} p-4 text-center">
                            <p class="text-3xl font-bold {{ count($importErrors) > 0 ? 'text-red-700' : 'text-gray-400' }}">
                                {{ count($importErrors) }}
                            </p>
                            <p class="mt-1 text-xs font-medium {{ count($importErrors) > 0 ? 'text-red-600' : 'text-gray-400' }}">
                                Rows skipped
                            </p>
                        </div>
                    </div>

                    @if (count($importErrors) > 0)
                        <div>
                            <p class="mb-2 text-sm font-medium text-gray-700">Skipped rows:</p>
                            <div class="max-h-56 overflow-y-auto rounded-lg border border-red-200">
                                <table class="min-w-full divide-y divide-red-100 text-xs">
                                    <thead class="sticky top-0 bg-red-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-semibold text-red-700 w-12">Row</th>
                                            <th class="px-3 py-2 text-left font-semibold text-red-700 w-40">Email</th>
                                            <th class="px-3 py-2 text-left font-semibold text-red-700">Errors</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-red-50 bg-white">
                                        @foreach ($importErrors as $err)
                                            <tr>
                                                <td class="px-3 py-2 text-gray-500">#{{ $err['row'] }}</td>
                                                <td class="px-3 py-2 text-gray-700 truncate max-w-[160px]">{{ $err['email'] }}</td>
                                                <td class="px-3 py-2 text-red-600">{{ implode(', ', $err['errors']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

            @endif

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    @if (! $importDone)
                        <x-button wire:click="$set('importModalOpen', false)" label="Cancel" flat />
                        <x-button
                            wire:click="processImport"
                            wire:loading.attr="disabled"
                            label="Import"
                            icon="arrow-up-tray"
                            primary
                            spinner="processImport"
                            :disabled="! $importFile"
                        />
                    @else
                        <x-button wire:click="openImport"                      label="Import Another File" secondary />
                        <x-button wire:click="$set('importModalOpen', false)"  label="Done"                primary />
                    @endif
                </div>
            </x-slot>

        </x-card>
    </x-modal>

</div>
