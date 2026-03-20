<?php

namespace App\Exports\Sheets;

use App\Models\Expense;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpensesDetailSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
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
        return 'Line Items';
    }

    public function collection(): Collection
    {
        return Expense::with('category')
            ->where('organization_id', $this->organizationId)
            ->whereYear('expense_date', $this->year)
            ->when($this->month,          fn ($q) => $q->whereMonth('expense_date', $this->month))
            ->when($this->filterDateFrom, fn ($q) => $q->whereDate('expense_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo,   fn ($q) => $q->whereDate('expense_date', '<=', $this->filterDateTo))
            ->orderBy('expense_date')
            ->get();
    }

    /** @return array<string> */
    public function headings(): array
    {
        return ['Title', 'Category', 'Description', 'Date', 'Amount'];
    }

    /** @return array<int, mixed> */
    public function map(mixed $expense): array
    {
        return [
            $expense->title,
            $expense->category?->name ?? '—',
            $expense->description ?? '',
            $expense->expense_date?->format('Y-m-d'),
            $expense->amount,
        ];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E11D48']],
        ]);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 30, 'B' => 20, 'C' => 40, 'D' => 15, 'E' => 15];
    }
}
