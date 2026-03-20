<?php

namespace App\Exports;

use App\Models\Pledge;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PledgesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        public readonly int $organizationId,
        public readonly string $year,
        public readonly ?int $filterMember,
        public readonly ?int $filterProject,
        public readonly string $filterStatus,
    ) {}

    public function title(): string
    {
        return 'Pledges';
    }

    public function collection(): Collection
    {
        return Pledge::with(['user', 'project'])
            ->where('organization_id', $this->organizationId)
            ->whereYear('pledge_date', $this->year)
            ->when($this->filterMember,  fn ($q) => $q->where('user_id', $this->filterMember))
            ->when($this->filterProject, fn ($q) => $q->where('project_id', $this->filterProject))
            ->when($this->filterStatus,  fn ($q) => $q->where('status', $this->filterStatus))
            ->orderBy('pledge_date')
            ->get();
    }

    /** @return array<string> */
    public function headings(): array
    {
        return [
            'Member',
            'Project',
            'Pledge Date',
            'Amount Pledged',
            'Amount Paid',
            'Outstanding Balance',
            'Status',
            'Deadline',
        ];
    }

    /** @return array<int, mixed> */
    public function map(mixed $pledge): array
    {
        $balance = max(0, $pledge->amount - $pledge->fulfilled_amount);

        return [
            $pledge->user?->name ?? '—',
            $pledge->project?->project_title ?? '—',
            $pledge->pledge_date?->format('Y-m-d'),
            $pledge->amount,
            $pledge->fulfilled_amount,
            $balance,
            ucfirst($pledge->status),
            $pledge->deadline?->format('Y-m-d') ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4F46E5']],
        ]);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 25,
            'C' => 15,
            'D' => 18,
            'E' => 15,
            'F' => 20,
            'G' => 12,
            'H' => 15,
        ];
    }
}
