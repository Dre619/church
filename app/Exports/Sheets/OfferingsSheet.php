<?php

namespace App\Exports\Sheets;

use App\Models\Payments;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OfferingsSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        public readonly int $organizationId,
        public readonly string $year,
        public readonly string $month,
        public readonly int $categoryId,
    ) {}

    public function title(): string
    {
        return 'Offerings';
    }

    public function collection(): Collection
    {
        return Payments::where('organization_id', $this->organizationId)
            ->where('category_id', $this->categoryId)
            ->whereYear('donation_date', $this->year)
            ->when($this->month, fn ($q) => $q->whereMonth('donation_date', $this->month))
            ->selectRaw("
                MONTH(donation_date)                                               AS month_num,
                MONTHNAME(donation_date)                                           AS month_name,
                SUM(CASE WHEN DAYOFWEEK(donation_date) = 1 THEN amount ELSE 0 END) AS sunday_total,
                SUM(CASE WHEN DAYOFWEEK(donation_date) != 1 THEN amount ELSE 0 END) AS midweek_total,
                SUM(amount)                                                         AS monthly_total
            ")
            ->groupByRaw('MONTH(donation_date), MONTHNAME(donation_date)')
            ->orderByRaw('MONTH(donation_date)')
            ->get();
    }

    /** @return array<string> */
    public function headings(): array
    {
        return ['Month', 'Sunday Service', 'Midweek & Other', 'Monthly Total'];
    }

    /** @return array<int, mixed> */
    public function map(mixed $row): array
    {
        return [
            $row->month_name,
            $row->sunday_total,
            $row->midweek_total,
            $row->monthly_total,
        ];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '7C3AED']],
        ]);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 18, 'B' => 20, 'C' => 22, 'D' => 18];
    }
}
