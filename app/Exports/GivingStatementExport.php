<?php

namespace App\Exports;

use App\Exports\Sheets\GivingSummarySheet;
use App\Exports\Sheets\GivingTransactionsSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GivingStatementExport implements WithMultipleSheets
{
    public function __construct(
        public readonly int $organizationId,
        public readonly int $memberId,
        public readonly string $memberName,
        public readonly string $memberEmail,
        public readonly int $year,
        public readonly string $currency,
    ) {}

    /** @return array<\Maatwebsite\Excel\Concerns\WithTitle> */
    public function sheets(): array
    {
        return [
            new GivingSummarySheet(
                $this->organizationId,
                $this->memberId,
                $this->memberName,
                $this->memberEmail,
                $this->year,
                $this->currency,
            ),
            new GivingTransactionsSheet(
                $this->organizationId,
                $this->memberId,
                $this->memberName,
                $this->year,
                $this->currency,
            ),
        ];
    }
}
