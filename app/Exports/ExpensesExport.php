<?php

namespace App\Exports;

use App\Exports\Sheets\ExpensesDetailSheet;
use App\Exports\Sheets\ExpensesSummarySheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ExpensesExport implements WithMultipleSheets
{
    public function __construct(
        public readonly int $organizationId,
        public readonly string $year,
        public readonly string $month,
        public readonly string $filterDateFrom,
        public readonly string $filterDateTo,
    ) {}

    /** @return array<\Maatwebsite\Excel\Concerns\WithTitle> */
    public function sheets(): array
    {
        return [
            new ExpensesSummarySheet(
                $this->organizationId,
                $this->year,
                $this->month,
                $this->filterDateFrom,
                $this->filterDateTo,
            ),
            new ExpensesDetailSheet(
                $this->organizationId,
                $this->year,
                $this->month,
                $this->filterDateFrom,
                $this->filterDateTo,
            ),
        ];
    }
}
