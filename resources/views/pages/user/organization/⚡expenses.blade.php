<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Organization;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use WireUi\Traits\WireUiActions;
use Illuminate\Support\Facades\Storage;

new class extends Component
{
    use WithPagination, WithFileUploads, WireUiActions;

    // ── Modals ────────────────────────────────────────────────────────────────
    public bool $modalOpen         = false;
    public bool $confirmDeleteOpen = false;
    public bool $viewModalOpen     = false;

    // ── Form ──────────────────────────────────────────────────────────────────
    public ?int    $editingId    = null;
    public ?int    $category_id  = null;
    public string  $title        = '';
    public string  $amount       = '';
    public string  $description  = '';
    public string  $expense_date = '';
    public         $image        = null;   // new upload
    public ?string $existingImage = null;  // current stored path

    // ── View ──────────────────────────────────────────────────────────────────
    public ?Expense $viewingExpense = null;

    // ── Filters ───────────────────────────────────────────────────────────────
    public string $search          = '';
    public string $filterCategory  = '';
    public string $filterDateFrom  = '';
    public string $filterDateTo    = '';

    // ── Delete ────────────────────────────────────────────────────────────────
    public ?int $deletingId = null;

    // ── Currency ──────────────────────────────────────────────────────────────
    public string $currency = 'ZMW';

    // ── Lookups ───────────────────────────────────────────────────────────────
    public array $categoryOptions = [];

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $organization_id = auth()->user()->myOrganization->organization_id;

        $this->currency = Organization::find($organization_id)?->currency ?? 'ZMW';

        $this->categoryOptions = ExpenseCategory::where('organization_id', $organization_id)
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])
            ->toArray();

        $this->expense_date = now()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'category_id'  => 'required|exists:expense_categories,id',
            'title'        => 'required|string|max:255',
            'amount'       => 'required|numeric|min:0.01',
            'description'  => 'nullable|string|max:2000',
            'expense_date' => 'required|date',
            'image'        => 'nullable|image|max:2048', // 2 MB
        ];
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getExpensesProperty()
    {
        $organization_id = auth()->user()->myOrganization->organization_id;

        return Expense::with('category')
            ->where('organization_id', $organization_id)
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('title', 'like', "%{$this->search}%")
                      ->orWhere('description', 'like', "%{$this->search}%")
                )
            )
            ->when($this->filterCategory, fn ($q) =>
                $q->where('category_id', $this->filterCategory)
            )
            ->when($this->filterDateFrom, fn ($q) =>
                $q->whereDate('expense_date', '>=', $this->filterDateFrom)
            )
            ->when($this->filterDateTo, fn ($q) =>
                $q->whereDate('expense_date', '<=', $this->filterDateTo)
            )
            ->latest('expense_date')
            ->paginate(10);
    }

    public function getTotalProperty(): float
    {
        $organization_id = auth()->user()->myOrganization->organization_id;

        return Expense::where('organization_id', $organization_id)
            ->when($this->filterCategory, fn ($q) =>
                $q->where('category_id', $this->filterCategory)
            )
            ->when($this->filterDateFrom, fn ($q) =>
                $q->whereDate('expense_date', '>=', $this->filterDateFrom)
            )
            ->when($this->filterDateTo, fn ($q) =>
                $q->whereDate('expense_date', '<=', $this->filterDateTo)
            )
            ->sum('amount');
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function updatingSearch(): void         { $this->resetPage(); }
    public function updatingFilterCategory(): void { $this->resetPage(); }
    public function updatingFilterDateFrom(): void { $this->resetPage(); }
    public function updatingFilterDateTo(): void   { $this->resetPage(); }

    // ── View ──────────────────────────────────────────────────────────────────

    public function openView(int $id): void
    {
        $this->viewingExpense = Expense::with('category')->findOrFail($id);
        $this->viewModalOpen  = true;
    }

    // ── Create / Edit ─────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        $this->modalOpen = true;
    }

    public function openEdit(int $id): void
    {
        $this->resetForm();
        $expense               = Expense::findOrFail($id);
        $this->editingId       = $expense->id;
        $this->category_id     = $expense->category_id;
        $this->title           = $expense->title;
        $this->amount          = $expense->amount;
        $this->description     = $expense->description ?? '';
        $this->expense_date    = $expense->expense_date->format('Y-m-d');
        $this->existingImage   = $expense->image_url;
        $this->modalOpen       = true;
    }

    public function save(): void
    {
        $this->validate();

        $organization_id = auth()->user()->myOrganization->organization_id;

        $imageUrl = $this->existingImage;

        if ($this->image) {
            // Delete old image if replacing
            if ($this->existingImage) {
                Storage::disk('public')->delete($this->existingImage);
            }
            $imageUrl = $this->image->store('expenses', 'public');
        }

        $data = [
            'organization_id' => $organization_id,
            'category_id'     => $this->category_id,
            'title'           => $this->title,
            'amount'          => $this->amount,
            'description'     => $this->description ?: null,
            'expense_date'    => $this->expense_date,
            'image_url'       => $imageUrl,
        ];

        if ($this->editingId) {
            Expense::findOrFail($this->editingId)->update($data);
            $this->notification()->success('Expense updated', "{$this->title} has been updated.");
        } else {
            Expense::create($data);
            $this->notification()->success('Expense recorded', "{$this->title} has been saved.");
        }

        $this->modalOpen = false;
        $this->resetForm();
        $this->resetPage();
    }

    public function removeExistingImage(): void
    {
        $this->existingImage = null;
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

        $expense = Expense::findOrFail($this->deletingId);

        if ($expense->image_url) {
            Storage::disk('public')->delete($expense->image_url);
        }

        $title = $expense->title;
        $expense->delete();

        $this->notification()->success('Expense deleted', "{$title} has been removed.");
        $this->confirmDeleteOpen = false;
        $this->deletingId        = null;
        $this->resetPage();
    }

    public function toggleReconcile(int $id): void
    {
        $expense = Expense::findOrFail($id);

        $expense->update([
            'reconciled'    => ! $expense->reconciled,
            'reconciled_at' => ! $expense->reconciled ? now() : null,
            'reconciled_by' => ! $expense->reconciled ? auth()->id() : null,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->editingId      = null;
        $this->category_id    = null;
        $this->title          = '';
        $this->amount         = '';
        $this->description    = '';
        $this->expense_date   = now()->format('Y-m-d');
        $this->image          = null;
        $this->existingImage  = null;
        $this->resetErrorBag();
    }

};
?>

<div class="min-h-screen bg-gray-50 p-6">

    {{-- ── Page Header ──────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Expenses</h1>
            <p class="mt-1 text-sm text-gray-500">Track and manage your organization's expenditures</p>
        </div>
        <x-button wire:click="openCreate" label="Record Expense" icon="plus" primary />
    </div>

    {{-- ── Filters ──────────────────────────────────────────────────────────── --}}
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <x-input
            wire:model.live.debounce.300ms="search"
            placeholder="Search expenses…"
            icon="magnifying-glass"
            class="max-w-xs"
        />

        <x-select wire:model.live="filterCategory" class="max-w-[180px]">
            <option value="">All categories</option>
            @foreach ($categoryOptions as $cat)
                <option value="{{ $cat['value'] }}">{{ $cat['label'] }}</option>
            @endforeach
        </x-select>

        <div class="flex items-center gap-2">
            <x-input wire:model.live="filterDateFrom" type="date" label="From" class="w-36" />
            <x-input wire:model.live="filterDateTo"   type="date" label="To"   class="w-36" />
        </div>

        @if ($filterCategory || $filterDateFrom || $filterDateTo || $search)
            <x-button
                wire:click="$set('search',''); $set('filterCategory',''); $set('filterDateFrom',''); $set('filterDateTo','')"
                label="Clear"
                flat
                icon="x-mark"
                sm
            />
        @endif
    </div>

    {{-- ── Summary Card ─────────────────────────────────────────────────────── --}}
    <div class="mb-5 flex items-center gap-3 rounded-xl border border-rose-100 bg-rose-50 px-5 py-4">
        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-rose-100 text-rose-600">
            <x-icon name="banknotes" class="h-5 w-5" />
        </div>
        <div>
            <p class="text-xs font-medium text-rose-500 uppercase tracking-wide">Total (filtered)</p>
            <p class="text-xl font-bold text-rose-700">{{ format_currency($this->total, $this->currency) }}</p>
        </div>
    </div>

    {{-- ── Table ────────────────────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Expense</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Receipt</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($this->expenses as $expense)
                    <tr wire:key="exp-{{ $expense->id }}" class="transition hover:bg-gray-50">

                        <td class="px-6 py-4">
                            <p class="font-medium text-gray-900">{{ $expense->title }}</p>
                            @if ($expense->description)
                                <p class="mt-0.5 max-w-xs truncate text-xs text-gray-400">{{ $expense->description }}</p>
                            @endif
                        </td>

                        <td class="px-6 py-4">
                            <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700">
                                {{ $expense->category?->name ?? '—' }}
                            </span>
                        </td>

                        <td class="px-6 py-4 font-semibold text-gray-900">
                            {{ format_currency($expense->amount, $this->currency) }}
                        </td>

                        <td class="px-6 py-4 text-gray-500">
                            {{ $expense->expense_date->format('M d, Y') }}
                        </td>

                        <td class="px-6 py-4">
                            @if ($expense->image_url)
                                <a href="{{ Storage::url($expense->image_url) }}" target="_blank"
                                   class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                    <x-icon name="paper-clip" class="h-3.5 w-3.5" />
                                    View
                                </a>
                            @else
                                <span class="text-xs text-gray-300">—</span>
                            @endif
                        </td>

                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button wire:click="toggleReconcile({{ $expense->id }})"
                                    title="{{ $expense->reconciled ? 'Mark as unreconciled' : 'Mark as reconciled' }}"
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg transition-colors {{ $expense->reconciled ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-400 hover:bg-gray-200' }}">
                                    <x-icon name="{{ $expense->reconciled ? 'check-circle' : 'ellipsis-horizontal-circle' }}" class="w-4 h-4" />
                                </button>
                                <x-mini-button wire:click="openView({{ $expense->id }})"      icon="eye"    flat secondary sm />
                                <x-mini-button wire:click="openEdit({{ $expense->id }})"      icon="pencil" flat secondary sm />
                                <x-mini-button wire:click="confirmDelete({{ $expense->id }})" icon="trash"  flat negative  sm />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <x-icon name="receipt-percent" class="h-10 w-10 opacity-40" />
                                <p class="text-sm font-medium">No expenses found</p>
                                @if ($search || $filterCategory || $filterDateFrom || $filterDateTo)
                                    <p class="text-xs">Try adjusting your filters</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($this->expenses->hasPages())
            <div class="border-t border-gray-100 px-6 py-4">
                {{ $this->expenses->links() }}
            </div>
        @endif
    </div>

    {{-- ── View Modal ───────────────────────────────────────────────────────── --}}
    <x-modal wire:model="viewModalOpen" max-width="lg">
        @if ($viewingExpense)
            <x-card class="relative" :title="$viewingExpense->title">
                <div class="space-y-4">

                    {{-- Receipt image --}}
                    @if ($viewingExpense->image_url)
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50">
                            <img
                                src="{{ Storage::url($viewingExpense->image_url) }}"
                                alt="Receipt"
                                class="max-h-64 w-full object-contain p-2"
                            />
                        </div>
                    @endif

                    {{-- Details grid --}}
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-xs font-medium uppercase text-gray-400">Category</p>
                            <p class="mt-1 text-gray-900">{{ $viewingExpense->category?->name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase text-gray-400">Amount</p>
                            <p class="mt-1 text-xl font-bold text-rose-600">{{ format_currency($viewingExpense->amount, $this->currency) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase text-gray-400">Date</p>
                            <p class="mt-1 text-gray-900">{{ $viewingExpense->expense_date->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase text-gray-400">Recorded</p>
                            <p class="mt-1 text-gray-900">{{ $viewingExpense->created_at->format('M d, Y') }}</p>
                        </div>
                    </div>

                    @if ($viewingExpense->description)
                        <div>
                            <p class="text-xs font-medium uppercase text-gray-400">Description</p>
                            <p class="mt-1 text-sm text-gray-700">{{ $viewingExpense->description }}</p>
                        </div>
                    @endif
                </div>

                <x-slot name="footer">
                    <div class="flex justify-end gap-3">
                        <x-button wire:click="openEdit({{ $viewingExpense->id }})" label="Edit" secondary />
                        <x-button wire:click="$set('viewModalOpen', false)" label="Close" flat />
                    </div>
                </x-slot>
            </x-card>
        @endif
    </x-modal>

    {{-- ── Create / Edit Modal ─────────────────────────────────────────────── --}}
    <x-modal wire:model="modalOpen" max-width="2xl">
        <x-card class="relative" :title="$editingId ? 'Edit Expense' : 'Record Expense'">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                {{-- Title --}}
                <div class="sm:col-span-2">
                    <x-input
                        wire:model="title"
                        label="Title"
                        placeholder="e.g. Electricity Bill — March"
                        icon="document-text"
                    />
                </div>

                {{-- Category --}}
                <x-select
                    wire:model="category_id"
                    label="Category"
                    placeholder="Select category"
                    :options="$categoryOptions"
                    option-value="value"
                    option-label="label"
                    searchable
                />

                {{-- Amount --}}
                <x-input
                    wire:model="amount"
                    label="Amount"
                    placeholder="0.00"
                    icon="currency-dollar"
                    type="number"
                    min="0"
                    step="0.01"
                />

                {{-- Date --}}
                <x-input
                    wire:model="expense_date"
                    label="Expense Date"
                    type="date"
                    icon="calendar"
                />

                {{-- Receipt upload --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Receipt / Image <span class="text-gray-400 font-normal">(optional, max 2 MB)</span>
                    </label>

                    {{-- Show existing image --}}
                    @if ($existingImage && ! $image)
                        <div class="mb-2 flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                            <img src="{{ Storage::url($existingImage) }}" alt="Receipt" class="h-10 w-10 rounded object-cover" />
                            <span class="flex-1 truncate text-xs text-gray-600">Current receipt</span>
                            <button wire:click="removeExistingImage" type="button" class="text-red-400 hover:text-red-600">
                                <x-icon name="x-mark" class="h-4 w-4" />
                            </button>
                        </div>
                    @endif

                    {{-- New upload preview --}}
                    @if ($image)
                        <div class="mb-2 flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2">
                            <img src="{{ $image->temporaryUrl() }}" alt="Preview" class="h-10 w-10 rounded object-cover" />
                            <span class="flex-1 truncate text-xs text-green-700">{{ $image->getClientOriginalName() }}</span>
                            <button wire:click="$set('image', null)" type="button" class="text-red-400 hover:text-red-600">
                                <x-icon name="x-mark" class="h-4 w-4" />
                            </button>
                        </div>
                    @endif

                    <label for="expense-image"
                        class="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-500 transition hover:border-primary-400 hover:bg-primary-50 hover:text-primary-600">
                        <x-icon name="arrow-up-tray" class="h-4 w-4" />
                        <span>{{ $image || $existingImage ? 'Replace image' : 'Upload receipt' }}</span>
                        <input id="expense-image" type="file" wire:model="image" accept="image/*" class="sr-only" />
                    </label>
                    @error('image') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Description --}}
                <div class="sm:col-span-2">
                    <x-textarea
                        wire:model="description"
                        label="Description (optional)"
                        placeholder="Additional notes about this expense…"
                        rows="3"
                    />
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('modalOpen', false)" label="Cancel" flat />
                    <x-button
                        wire:click="save"
                        wire:loading.attr="disabled"
                        label="{{ $editingId ? 'Update Expense' : 'Save Expense' }}"
                        primary
                        spinner="save"
                    />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

    {{-- ── Confirm Delete ───────────────────────────────────────────────────── --}}
    <x-modal wire:model="confirmDeleteOpen" max-width="sm">
        <x-card class="relative" title="Delete Expense">
            <p class="text-sm text-gray-600">
                Are you sure you want to delete this expense? Any attached receipt will also be removed.
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
