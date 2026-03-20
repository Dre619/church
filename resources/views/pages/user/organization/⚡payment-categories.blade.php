<?php

use App\Models\PaymentCategory;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new class extends Component
{
    use WithPagination, WireUiActions;

    // ── Modals ────────────────────────────────────────────────────────────────
    public bool $modalOpen         = false;
    public bool $confirmDeleteOpen = false;

    // ── Form ─────────────────────────────────────────────────────────────────
    public ?int   $editingId = null;
    public string $name      = '';
    public bool   $is_active = true;

    // ── Delete ───────────────────────────────────────────────────────────────
    public ?int $deletingId = null;

    // ── Search / Filter ───────────────────────────────────────────────────────
    public string $search    = '';
    public string $filterStatus = ''; // '', '1', '0'
    public $lockedCategories = [
                    'Offering',
                    'Tithe',
                    'Pledges',
                    'Donation'
                ];

    // ─────────────────────────────────────────────────────────────────────────

    protected function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'is_active' => 'boolean',
        ];
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getCategoriesProperty()
    {
        $organization_id = auth()->user()->myOrganization->organization_id;

        return PaymentCategory::where('organization_id', $organization_id)
            ->when($this->search, fn ($q) =>
                $q->where('name', 'like', "%{$this->search}%")
            )
            ->when($this->filterStatus !== '', fn ($q) =>
                $q->where('is_active', (bool) $this->filterStatus)
            )
            ->latest()
            ->paginate(10);
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }

    // ── Create / Edit ─────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        $this->modalOpen = true;
    }

    public function openEdit(int $id): void
    {
        $this->resetForm();
        $category        = PaymentCategory::findOrFail($id);
        $this->editingId = $category->id;
        $this->name      = $category->name;
        $this->is_active = $category->is_active;
        $this->modalOpen = true;
    }

    public function save(): void
    {
        $this->validate();

        $organization_id = auth()->user()->myOrganization->organization_id;

        if ($this->editingId) {
            $category = PaymentCategory::findOrFail($this->editingId);
            $category->update([
                'name'      => $this->name,
                'is_active' => $this->is_active,
            ]);
            $this->notification()->success('Category updated', "{$category->name} has been updated.");
        } else {
            $category = PaymentCategory::create([
                'organization_id' => $organization_id,
                'name'            => $this->name,
                'is_active'       => $this->is_active,
            ]);
            $this->notification()->success('Category created', "{$category->name} has been added.");
        }

        $this->modalOpen = false;
        $this->resetForm();
        $this->resetPage();
    }

    // ── Toggle active inline ──────────────────────────────────────────────────

    public function toggleActive(int $id): void
    {
        $category = PaymentCategory::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);

        $this->notification()->success(
            $category->is_active ? 'Category activated' : 'Category deactivated',
            $category->name
        );
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $this->deletingId      = $id;
        $this->confirmDeleteOpen = true;
    }

    public function delete(): void
    {
        if (! $this->deletingId) return;

        $category = PaymentCategory::findOrFail($this->deletingId);
        $name     = $category->name;
        $category->delete();

        $this->notification()->success('Category deleted', "{$name} has been deleted.");
        $this->confirmDeleteOpen = false;
        $this->deletingId        = null;
        $this->resetPage();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name      = '';
        $this->is_active = true;
        $this->resetErrorBag();
    }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    {{-- ── Page Header ──────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Payment Categories</h1>
            <p class="mt-1 text-sm text-gray-500">Manage categories used for payments in your organization</p>
        </div>
        <x-button wire:click="openCreate" label="Add Category" icon="plus" primary />
    </div>

    {{-- ── Filters ──────────────────────────────────────────────────────────── --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <x-input
            wire:model.live.debounce.300ms="search"
            placeholder="Search categories…"
            icon="magnifying-glass"
            class="max-w-xs"
        />

        <x-select wire:model.live="filterStatus" class="max-w-[160px]">
            <option value="">All statuses</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </x-select>
    </div>

    {{-- ── Table ────────────────────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Created</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($this->categories as $category)
                    <tr wire:key="cat-{{ $category->id }}" class="transition hover:bg-gray-50">

                        {{-- Name --}}
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
                                    <x-icon name="tag" class="h-4 w-4" />
                                </div>
                                <span class="font-medium text-gray-900">{{ $category->name }}</span>
                            </div>
                        </td>

                        {{-- Status toggle --}}
                        <td class="px-6 py-4">
                            <button
                                wire:click="toggleActive({{ $category->id }})"
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium transition
                                    {{ $category->is_active
                                        ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                        : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}"
                            >
                                <span class="h-1.5 w-1.5 rounded-full {{ $category->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                {{ $category->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </td>

                        {{-- Created --}}
                        <td class="px-6 py-4 text-gray-500">{{ $category->created_at->format('M d, Y') }}</td>

                        {{-- Actions --}}
                        <td class="px-6 py-4 text-right">
                            @if(!in_array($category->name,$lockedCategories))
                            <div class="flex items-center justify-end gap-2">
                                <x-mini-button wire:click="openEdit({{ $category->id }})"    icon="pencil" flat secondary sm />
                                <x-mini-button wire:click="confirmDelete({{ $category->id }})" icon="trash"  flat negative  sm />
                            </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <x-icon name="tag" class="h-10 w-10 opacity-40" />
                                <p class="text-sm font-medium">No categories found</p>
                                @if ($search)
                                    <p class="text-xs">Try a different search term</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($this->categories->hasPages())
            <div class="border-t border-gray-100 px-6 py-4">
                {{ $this->categories->links() }}
            </div>
        @endif
    </div>

    {{-- ── Create / Edit Modal ─────────────────────────────────────────────── --}}
    <x-modal wire:model="modalOpen" max-width="md">
        <x-card :title="$editingId ? 'Edit Category' : 'New Payment Category'" class="relative">
            <div class="space-y-4">
                <x-input
                    wire:model="name"
                    label="Category Name"
                    placeholder="e.g. Tithe, Offering, Donation…"
                    icon="tag"
                />

                <x-toggle
                    wire:model="is_active"
                    label="Active"
                    hint="Inactive categories won't appear in payment forms"
                />
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('modalOpen', false)" label="Cancel" flat />
                    <x-button
                        wire:click="save"
                        wire:loading.attr="disabled"
                        label="{{ $editingId ? 'Update Category' : 'Create Category' }}"
                        primary
                        spinner="save"
                    />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

    {{-- ── Confirm Delete ───────────────────────────────────────────────────── --}}
    <x-modal wire:model="confirmDeleteOpen" max-width="sm">
        <x-card title="Delete Category" class="relative">
            <p class="text-sm text-gray-600">
                Are you sure you want to delete this category? This action cannot be undone and may affect existing payment records.
            </p>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('confirmDeleteOpen', false)" label="Cancel" flat />
                    <x-button wire:click="delete" wire:loading.attr="disabled" label="Delete" negative spinner="delete" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

</div>
