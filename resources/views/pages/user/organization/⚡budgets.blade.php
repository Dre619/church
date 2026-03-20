<?php

use App\Models\Budget;
use App\Models\ExpenseCategory;
use App\Models\Organization;
use App\Models\PaymentCategory;
use App\Models\Payments;
use App\Models\Expense;
use Livewire\Component;
use WireUi\Traits\WireUiActions;

new class extends Component
{
    use WireUiActions;

    public string $currency  = 'ZMW';
    public int    $year;
    public string $tab       = 'income'; // income | expense

    // Form
    public bool    $modalOpen   = false;
    public ?int    $editingId   = null;
    public string  $type        = 'income';
    public ?int    $category_id = null;
    public string  $name        = '';
    public string  $amount      = '';

    public array $incomeCategories  = [];
    public array $expenseCategories = [];

    public function mount(): void
    {
        $this->year = (int) now()->format('Y');

        $orgId = auth()->user()->myOrganization->organization_id;
        $this->currency = Organization::find($orgId)?->currency ?? 'ZMW';

        $this->incomeCategories = PaymentCategory::where('organization_id', $orgId)
            ->where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))
            ->orderBy('name')
            ->pluck('name', 'id')->toArray();

        $this->expenseCategories = ExpenseCategory::where('organization_id', $orgId)
            ->orderBy('name')
            ->pluck('name', 'id')->toArray();
    }

    public function getOrgIdProperty(): int
    {
        return auth()->user()->myOrganization->organization_id;
    }

    // ── Computed rows with actuals ─────────────────────────────────────────

    public function getIncomeBudgetsProperty()
    {
        $rows = Budget::where('organization_id', $this->orgId)
            ->where('type', 'income')
            ->where('year', $this->year)
            ->whereNull('month')
            ->get();

        return $rows->map(function (Budget $b) {
            $actual = Payments::where('organization_id', $this->orgId)
                ->whereYear('donation_date', $this->year)
                ->when($b->category_id, fn ($q) => $q->where('category_id', $b->category_id))
                ->sum('amount');

            return [
                'id'         => $b->id,
                'name'       => $b->name,
                'budget'     => (float) $b->amount,
                'actual'     => (float) $actual,
                'variance'   => (float) $actual - (float) $b->amount,
                'pct'        => $b->amount > 0 ? min(200, ($actual / $b->amount) * 100) : 0,
            ];
        });
    }

    public function getExpenseBudgetsProperty()
    {
        $rows = Budget::where('organization_id', $this->orgId)
            ->where('type', 'expense')
            ->where('year', $this->year)
            ->whereNull('month')
            ->get();

        return $rows->map(function (Budget $b) {
            $actual = Expense::where('organization_id', $this->orgId)
                ->whereYear('expense_date', $this->year)
                ->when($b->category_id, fn ($q) => $q->where('category_id', $b->category_id))
                ->sum('amount');

            return [
                'id'       => $b->id,
                'name'     => $b->name,
                'budget'   => (float) $b->amount,
                'actual'   => (float) $actual,
                'variance' => (float) $b->amount - (float) $actual, // positive = under budget
                'pct'      => $b->amount > 0 ? min(200, ($actual / $b->amount) * 100) : 0,
            ];
        });
    }

    // ── CRUD ──────────────────────────────────────────────────────────────

    public function openCreate(string $type): void
    {
        $this->reset(['editingId', 'category_id', 'name', 'amount']);
        $this->type      = $type;
        $this->modalOpen = true;
    }

    public function openEdit(int $id): void
    {
        $b = Budget::findOrFail($id);
        $this->editingId   = $b->id;
        $this->type        = $b->type;
        $this->category_id = $b->category_id;
        $this->name        = $b->name;
        $this->amount      = $b->amount;
        $this->modalOpen   = true;
    }

    public function save(): void
    {
        $this->validate([
            'name'   => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'type'   => 'required|in:income,expense',
        ]);

        $data = [
            'organization_id' => $this->orgId,
            'type'            => $this->type,
            'category_id'     => $this->category_id,
            'name'            => $this->name,
            'amount'          => $this->amount,
            'year'            => $this->year,
            'month'           => null,
        ];

        if ($this->editingId) {
            Budget::findOrFail($this->editingId)->update($data);
            $this->notification()->success('Updated', 'Budget line updated.');
        } else {
            Budget::create($data);
            $this->notification()->success('Created', 'Budget line added.');
        }

        $this->modalOpen = false;
        $this->reset(['editingId', 'category_id', 'name', 'amount']);
    }

    public function delete(int $id): void
    {
        Budget::findOrFail($id)->delete();
        $this->notification()->success('Deleted', 'Budget line removed.');
    }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Budget Management</h1>
            <p class="mt-1 text-sm text-gray-500">Set annual income & expense budgets and track actuals vs targets</p>
        </div>
        <div class="flex items-center gap-3">
            <x-select wire:model.live="year" class="w-28"
                :options="collect(range(now()->year - 2, now()->year + 1))->map(fn ($y) => ['value' => $y, 'label' => $y])->toArray()"
                option-value="value" option-label="label"
            />
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 bg-gray-100 rounded-xl p-1 w-fit mb-6">
        <button wire:click="$set('tab','income')"
            class="px-5 py-2 rounded-lg text-sm font-medium transition-all {{ $tab === 'income' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700' }}">
            Income Budgets
        </button>
        <button wire:click="$set('tab','expense')"
            class="px-5 py-2 rounded-lg text-sm font-medium transition-all {{ $tab === 'expense' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700' }}">
            Expense Budgets
        </button>
    </div>

    {{-- Budget Table --}}
    @php $rows = $tab === 'income' ? $this->incomeBudgets : $this->expenseBudgets; @endphp

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm mb-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <p class="font-semibold text-gray-800 text-sm uppercase tracking-wide">
                {{ $tab === 'income' ? 'Income' : 'Expense' }} Budgets — {{ $year }}
            </p>
            <x-button wire:click="openCreate('{{ $tab }}')" icon="plus" primary sm label="Add Budget Line" />
        </div>

        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Line Item</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Budget</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actual</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Variance</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Progress</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 bg-white">
                @forelse ($rows as $row)
                    @php
                        $over     = $tab === 'expense' ? $row['variance'] < 0 : $row['variance'] >= 0;
                        $barColor = $tab === 'income'
                            ? ($row['pct'] >= 100 ? 'bg-green-500' : 'bg-blue-400')
                            : ($row['pct'] > 100 ? 'bg-red-500' : ($row['pct'] > 80 ? 'bg-yellow-400' : 'bg-green-400'));
                    @endphp
                    <tr class="hover:bg-gray-50" wire:key="budget-{{ $row['id'] }}">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $row['name'] }}</td>
                        <td class="px-6 py-4 text-right text-gray-700">{{ format_currency($row['budget'], $currency) }}</td>
                        <td class="px-6 py-4 text-right font-semibold text-gray-900">{{ format_currency($row['actual'], $currency) }}</td>
                        <td class="px-6 py-4 text-right font-semibold {{ $row['variance'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $row['variance'] >= 0 ? '+' : '' }}{{ format_currency($row['variance'], $currency) }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200">
                                    <div class="h-2 rounded-full {{ $barColor }}" style="width: {{ min(100, $row['pct']) }}%"></div>
                                </div>
                                <span class="w-10 text-right text-xs text-gray-500">{{ number_format($row['pct'], 0) }}%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2 justify-end">
                                <x-button wire:click="openEdit({{ $row['id'] }})" icon="pencil" flat xs />
                                <x-button wire:click="delete({{ $row['id'] }})" wire:confirm="Delete this budget line?" icon="trash" flat negative xs />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <x-icon name="calculator" class="h-10 w-10 opacity-40" />
                                <p class="text-sm">No {{ $tab }} budgets set for {{ $year }}. Add one to start tracking.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                    <tr>
                        <td class="px-6 py-3 text-xs font-bold uppercase text-gray-500">Totals</td>
                        <td class="px-6 py-3 text-right font-bold text-gray-800">{{ format_currency($rows->sum('budget'), $currency) }}</td>
                        <td class="px-6 py-3 text-right font-bold text-gray-900">{{ format_currency($rows->sum('actual'), $currency) }}</td>
                        <td class="px-6 py-3 text-right font-bold {{ $rows->sum('variance') >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $rows->sum('variance') >= 0 ? '+' : '' }}{{ format_currency($rows->sum('variance'), $currency) }}
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    {{-- Add/Edit Modal --}}
    <x-modal wire:model.defer="modalOpen" max-width="md">
        <x-card class="relative" :title="$editingId ? 'Edit Budget Line' : 'Add Budget Line'">
            <div class="space-y-4">
                <x-input label="Line Item Name *" wire:model="name" placeholder="e.g. Tithe Budget" />
                @error('name') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror

                @if ($type === 'income')
                    <x-select label="Income Category (optional)" wire:model="category_id" placeholder="— All income —"
                        :options="collect($incomeCategories)->map(fn ($name, $id) => ['value' => $id, 'label' => $name])->values()->toArray()"
                        option-value="value" option-label="label"
                    />
                @else
                    <x-select label="Expense Category (optional)" wire:model="category_id" placeholder="— All expenses —"
                        :options="collect($expenseCategories)->map(fn ($name, $id) => ['value' => $id, 'label' => $name])->values()->toArray()"
                        option-value="value" option-label="label"
                    />
                @endif

                <x-input label="Annual Budget Amount *" wire:model="amount" placeholder="0.00" type="number" step="0.01" />
                @error('amount') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button flat label="Cancel" wire:click="$set('modalOpen', false)" />
                    <x-button primary label="{{ $editingId ? 'Update' : 'Add' }}" wire:click="save" spinner="save" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>
<x-spinner/>
    <x-notifications />
</div>
