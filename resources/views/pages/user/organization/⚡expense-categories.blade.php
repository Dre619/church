<?php

use App\Models\ExpenseCategory;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new class extends Component
{
    use WithPagination, WireUiActions;

    // ── Modals ────────────────────────────────────────────────────────────────
    public bool $modalOpen         = false;
    public bool $confirmDeleteOpen = false;

    // ── Form ──────────────────────────────────────────────────────────────────
    public ?int    $editingId   = null;
    public string  $name        = '';
    public string  $description = '';

    // ── Filters ───────────────────────────────────────────────────────────────
    public string $search = '';

    // ── Delete ────────────────────────────────────────────────────────────────
    public ?int $deletingId = null;

    // ─────────────────────────────────────────────────────────────────────────

    protected function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getCategoriesProperty()
    {
        $organization_id = auth()->user()->myOrganization->organization_id;

        return ExpenseCategory::withCount('expenses')
            ->where('organization_id', $organization_id)
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('description', 'like', "%{$this->search}%")
                )
            )
            ->latest()
            ->paginate(10);
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function updatingSearch(): void { $this->resetPage(); }

    // ── Create / Edit ─────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        $this->modalOpen = true;
    }

    public function openEdit(int $id): void
    {
        $this->resetForm();
        $category          = ExpenseCategory::findOrFail($id);
        $this->editingId   = $category->id;
        $this->name        = $category->name;
        $this->description = $category->description ?? '';
        $this->modalOpen   = true;
    }

    public function save(): void
    {
        $this->validate();

        $organization_id = auth()->user()->myOrganization->organization_id;

        if ($this->editingId) {
            ExpenseCategory::findOrFail($this->editingId)->update([
                'name'        => $this->name,
                'description' => $this->description ?: null,
            ]);
            $this->notification()->success('Category updated', "{$this->name} has been updated.");
        } else {
            ExpenseCategory::create([
                'organization_id' => $organization_id,
                'name'            => $this->name,
                'description'     => $this->description ?: null,
            ]);
            $this->notification()->success('Category created', "{$this->name} has been added.");
        }

        $this->modalOpen = false;
        $this->resetForm();
        $this->resetPage();
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

        $category = ExpenseCategory::findOrFail($this->deletingId);
        $name     = $category->name;
        $category->delete();

        $this->notification()->success('Category deleted', "{$name} has been removed.");
        $this->confirmDeleteOpen = false;
        $this->deletingId        = null;
        $this->resetPage();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->editingId   = null;
        $this->name        = '';
        $this->description = '';
        $this->resetErrorBag();
    }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    {{-- ── Page Header ──────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Expense Categories</h1>
            <p class="mt-1 text-sm text-gray-500">Organise your expenses into categories</p>
        </div>
        <x-button wire:click="openCreate" label="Add Category" icon="plus" primary />
    </div>

    {{-- ── Search ───────────────────────────────────────────────────────────── --}}
    <div class="mb-4">
        <x-input
            wire:model.live.debounce.300ms="search"
            placeholder="Search categories…"
            icon="magnifying-glass"
            class="max-w-xs"
        />
    </div>

    {{-- ── Table ────────────────────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Expenses</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Created</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($this->categories as $category)
                    <tr wire:key="ecat-{{ $category->id }}" class="transition hover:bg-gray-50">

                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-500">
                                    <x-icon name="folder" class="h-4 w-4" />
                                </div>
                                <span class="font-medium text-gray-900">{{ $category->name }}</span>
                            </div>
                        </td>

                        <td class="px-6 py-4 max-w-xs">
                            <p class="truncate text-gray-500">{{ $category->description ?? '—' }}</p>
                        </td>

                        <td class="px-6 py-4">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                                {{ $category->expenses_count }}
                            </span>
                        </td>

                        <td class="px-6 py-4 text-gray-500">{{ $category->created_at->format('M d, Y') }}</td>

                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <x-mini-button wire:click="openEdit({{ $category->id }})"      icon="pencil" flat secondary sm />
                                <x-mini-button wire:click="confirmDelete({{ $category->id }})" icon="trash"  flat negative  sm />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <x-icon name="folder-open" class="h-10 w-10 opacity-40" />
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
        <x-card class="relative" :title="$editingId ? 'Edit Category' : 'New Expense Category'">
            <div class="space-y-4">
                <x-input
                    wire:model="name"
                    label="Category Name"
                    placeholder="e.g. Utilities, Salaries, Maintenance…"
                    icon="folder"
                />
                <x-textarea
                    wire:model="description"
                    label="Description (optional)"
                    placeholder="Brief description of this category…"
                    rows="3"
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
        <x-card class="relative" title="Delete Category">
            <p class="text-sm text-gray-600">
                Are you sure you want to delete this category? Expenses linked to it will lose their category reference.
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
