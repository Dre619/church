<?php

namespace App\Exports\Sheets;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpensesSummarySheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        public readonly int $organizationId,
        public readonly string $year,
        public readonly string $month,
        public readonly string $filterDateFrom,
        public readonly string $filterDateTo,
    ) {}

    public function title(): string
    {
        return 'Summary';
    }

    public function collection(): Collection
    {
        return ExpenseCategory::where('organization_id', $this->organizationId)
            ->withSum(['expenses' => function ($q) {
                $q->where('expenses.organization_id', $this->organizationId)
                    ->whereYear('expense_date', $this->year)
                    ->when($this->month,          fn ($q) => $q->whereMonth('expense_date', $this->month))
                    ->when($this->filterDateFrom, fn ($q) => $q->whereDate('expense_date', '>=', $this->filterDateFrom))
                    ->when($this->filterDateTo,   fn ($q) => $q->whereDate('expense_date', '<=', $this->filterDateTo));
            }], 'amount')
            ->withCount(['expenses' => function ($q) {
                $q->where('expenses.organization_id', $this->organizationId)
                    ->whereYear('expense_date', $this->year)
                    ->when($this->month,          fn ($q) => $q->whereMonth('expense_date', $this->month))
                    ->when($this->filterDateFrom, fn ($q) => $q->whereDate('expense_date', '>=', $this->filterDateFrom))
                    ->when($this->filterDateTo,   fn ($q) => $q->whereDate('expense_date', '<=', $this->filterDateTo));
            }])
            ->orderBy('name')
            ->get();
    }

    /** @return array<string> */
    public function headings(): array
    {
        return ['Category', 'Number of Expenses', 'Total Amount'];
    }

    /** @return array<int, mixed> */
    public function map(mixed $category): array
    {
        return [
            $category->name,
            $category->expenses_count,
            $category->expenses_sum_amount ?? 0,
        ];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E11D48']],
        ]);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 25, 'B' => 22, 'C' => 18];
    }
}
