<?php

namespace App\Exports\Sheets;

use App\Models\Payments;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GivingSummarySheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function __construct(
        public readonly int $organizationId,
        public readonly int $memberId,
        public readonly string $memberName,
        public readonly string $memberEmail,
        public readonly int $year,
        public readonly string $currency,
    ) {}

    public function title(): string
    {
        return 'Summary';
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $breakdown = Payments::with('category')
            ->where('organization_id', $this->organizationId)
            ->where(fn ($q) => $q->where('user_id', $this->memberId)->orWhere('name', $this->memberName))
            ->whereYear('donation_date', $this->year)
            ->get()
            ->groupBy(fn ($p) => $p->category?->name ?? 'Uncategorised')
            ->map(fn ($group, $cat) => [
                'category' => $cat,
                'count'    => $group->count(),
                'total'    => $group->sum('amount'),
            ])
            ->values();

        $total = $breakdown->sum('total');
        $count = $breakdown->sum('count');

        $rows = [
            ['Member', $this->memberName],
            ['Email', $this->memberEmail],
            ['Tax Year', $this->year],
            ['Generated', now()->format('F j, Y')],
            [],
            ['Category', 'Payments', 'Total (' . $this->currency . ')'],
        ];

        foreach ($breakdown as $row) {
            $rows[] = [$row['category'], $row['count'], $row['total']];
        }

        $rows[] = ['TOTAL', $count, $total];

        return $rows;
    }

    public function styles(Worksheet $sheet): void
    {
        // Meta header block
        $sheet->getStyle('A1:A4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '64748B']],
        ]);

        // Column headers
        $lastDataRow = $sheet->getHighestRow();
        $headerRow   = 6;

        $sheet->getStyle("A{$headerRow}:C{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E3A5F']],
        ]);

        // Totals row
        $sheet->getStyle("A{$lastDataRow}:C{$lastDataRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'DCFCE7']],
        ]);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 30, 'B' => 12, 'C' => 20];
    }
}
