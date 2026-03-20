<?php

namespace App\Exports\Sheets;

use App\Models\Payments;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GivingTransactionsSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function __construct(
        public readonly int $organizationId,
        public readonly int $memberId,
        public readonly string $memberName,
        public readonly int $year,
        public readonly string $currency,
    ) {}

    public function title(): string
    {
        return 'Transactions';
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $payments = Payments::with('category')
            ->where('organization_id', $this->organizationId)
            ->where(fn ($q) => $q->where('user_id', $this->memberId)->orWhere('name', $this->memberName))
            ->whereYear('donation_date', $this->year)
            ->orderBy('donation_date')
            ->get();

        $rows = [
            ['Date', 'Category', 'Payment Method', 'Reference', 'Amount (' . $this->currency . ')'],
        ];

        foreach ($payments as $payment) {
            $rows[] = [
                $payment->donation_date?->format('Y-m-d') ?? '—',
                $payment->category?->name ?? 'Uncategorised',
                ucwords(str_replace('_', ' ', $payment->payment_method ?? '—')),
                $payment->transaction_id ?? '',
                $payment->amount,
            ];
        }

        $rows[] = ['', '', '', 'TOTAL', $payments->sum('amount')];

        return $rows;
    }

    public function styles(Worksheet $sheet): void
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E3A5F']],
        ]);

        $sheet->getStyle("A{$lastRow}:E{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'DCFCE7']],
        ]);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 22, 'C' => 20, 'D' => 24, 'E' => 18];
    }
}
