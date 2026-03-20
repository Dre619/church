<?php

namespace App\Exports\Sheets;

use App\Models\Payments;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CollectionsMemberSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        public readonly int $organizationId,
        public readonly string $year,
        public readonly string $month,
        public readonly int $categoryId,
        public readonly string $categoryName,
    ) {}

    public function title(): string
    {
        return substr($this->categoryName, 0, 31);
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $monthLabels = array_map(
            fn ($m) => \Carbon\Carbon::create()->month($m)->format('M'),
            range(1, 12),
        );

        // Header row
        $rows = [array_merge(['Member'], $monthLabels, ['Total'])];

        $data = Payments::where('payments.organization_id', $this->organizationId)
            ->where('payments.category_id', $this->categoryId)
            ->whereYear('donation_date', $this->year)
            ->when($this->month, fn ($q) => $q->whereMonth('donation_date', $this->month))
            ->join('users', 'payments.user_id', '=', 'users.id')
            ->selectRaw('users.name AS member_name, MONTH(donation_date) AS month_num, SUM(payments.amount) AS total')
            ->groupByRaw('users.id, users.name, MONTH(donation_date)')
            ->orderByRaw('users.name, MONTH(donation_date)')
            ->get();

        // Group by member
        $grouped = [];
        foreach ($data as $row) {
            $grouped[$row->member_name][$row->month_num] = $row->total;
        }

        $grandTotal = 0;
        $colTotals  = array_fill(1, 12, 0);

        foreach ($grouped as $memberName => $monthTotals) {
            $memberTotal = array_sum($monthTotals);
            $grandTotal += $memberTotal;

            $dataRow = [$memberName];
            foreach (range(1, 12) as $mn) {
                $val = $monthTotals[$mn] ?? 0;
                $colTotals[$mn] += $val;
                $dataRow[] = $val ?: '';
            }
            $dataRow[] = $memberTotal;
            $rows[]    = $dataRow;
        }

        // Totals row
        $totalsRow = ['Consolidated Total'];
        foreach (range(1, 12) as $mn) {
            $totalsRow[] = $colTotals[$mn] ?: '';
        }
        $totalsRow[] = $grandTotal;
        $rows[]      = $totalsRow;

        return $rows;
    }

    public function styles(Worksheet $sheet): void
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        // Header row
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0369A1']],
        ]);

        // Totals row
        $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F1F5F9']],
        ]);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 25, 'N' => 15];
    }
}
