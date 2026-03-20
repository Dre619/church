<?php

namespace App\Exports;

use App\Models\OrganizationUser;
use App\Models\Payments;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AllGivingStatementsExport implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function __construct(
        public readonly int $organizationId,
        public readonly int $year,
        public readonly string $currency,
    ) {}

    public function title(): string
    {
        return "Giving {$this->year}";
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $members = User::whereHas(
            'myOrganizations',
            fn ($q) => $q->where('organization_id', $this->organizationId)
        )
            ->withSum(['payments as total_given' => fn ($q) =>
                $q->where('organization_id', $this->organizationId)
                    ->whereYear('donation_date', $this->year)
            ], 'amount')
            ->orderBy('name')
            ->get();

        $rows = [
            ['Member', 'Email', "Total Given ({$this->year})", 'Currency'],
        ];

        foreach ($members as $member) {
            $rows[] = [
                $member->name,
                $member->email,
                $member->total_given ?? 0,
                $this->currency,
            ];
        }

        $rows[] = ['TOTAL', '', $members->sum('total_given'), $this->currency];

        return $rows;
    }

    public function styles(Worksheet $sheet): void
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E3A5F']],
        ]);

        $sheet->getStyle("A{$lastRow}:D{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'DCFCE7']],
        ]);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 30, 'B' => 32, 'C' => 22, 'D' => 12];
    }
}
