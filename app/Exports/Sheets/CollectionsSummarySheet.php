<?php

namespace App\Exports\Sheets;

use App\Models\Payments;
use App\Models\PaymentCategory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CollectionsSummarySheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        public readonly int $organizationId,
        public readonly string $year,
        public readonly string $month,
    ) {}

    public function title(): string
    {
        return 'Summary';
    }

    public function collection(): Collection
    {
        return PaymentCategory::where('organization_id', $this->organizationId)
            ->where('is_active', true)
            ->withSum(['payments' => function ($q) {
                $q->where('payments.organization_id', $this->organizationId)
                    ->whereYear('donation_date', $this->year)
                    ->when($this->month, fn ($q) => $q->whereMonth('donation_date', $this->month));
            }], 'amount')
            ->orderBy('name')
            ->get();
    }

    /** @return array<string> */
    public function headings(): array
    {
        $period = $this->month
            ? \Carbon\Carbon::create()->month((int) $this->month)->format('F') . ' ' . $this->year
            : $this->year;

        return [
            'Category',
            "Total Collected ({$period})",
        ];
    }

    /** @return array<int, mixed> */
    public function map(mixed $category): array
    {
        return [
            $category->name,
            $category->payments_sum_amount ?? 0,
        ];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '059669']],
        ]);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 25, 'B' => 25];
    }
}
