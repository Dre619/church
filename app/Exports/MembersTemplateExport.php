<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Collection;

class MembersTemplateExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    ShouldAutoSize
{
    public function collection(): Collection
    {
        // Return two sample rows so the user understands the format
        return collect([
            ['Jane Smith', 'jane@example.com', 'SecurePass123'],
            ['John Doe',   'john@example.com', 'SecurePass456'],
        ]);
    }

    public function headings(): array
    {
        return ['Full Name', 'Email Address', 'Password'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Header row: bold white text on dark blue
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E40AF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            // Sample rows: light blue tint
            2 => ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEFF6FF']]],
            3 => ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFFFF']]],
        ];
    }
}
