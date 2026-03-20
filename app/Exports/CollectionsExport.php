<?php

namespace App\Exports;

use App\Models\PaymentCategory;
use App\Exports\Sheets\CollectionsSummarySheet;
use App\Exports\Sheets\OfferingsSheet;
use App\Exports\Sheets\CollectionsMemberSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CollectionsExport implements WithMultipleSheets
{
    /** @var array<string> */
    private array $offeringNames = ['offering', 'offerings'];

    public function __construct(
        public readonly int $organizationId,
        public readonly string $year,
        public readonly string $month,
    ) {}

    /** @return array<\Maatwebsite\Excel\Concerns\WithTitle> */
    public function sheets(): array
    {
        $sheets = [
            new CollectionsSummarySheet($this->organizationId, $this->year, $this->month),
        ];

        $offeringCategory = PaymentCategory::where('organization_id', $this->organizationId)
            ->whereIn('name', ['Offering', 'Offerings', 'offering', 'offerings'])
            ->first();

        if ($offeringCategory) {
            $sheets[] = new OfferingsSheet($this->organizationId, $this->year, $this->month, $offeringCategory->id);
        }

        $otherCategories = PaymentCategory::where('organization_id', $this->organizationId)
            ->where('is_active', true)
            ->whereNotIn('name', ['Offering', 'Offerings', 'offering', 'offerings'])
            ->orderBy('name')
            ->get();

        foreach ($otherCategories as $category) {
            $sheets[] = new CollectionsMemberSheet(
                $this->organizationId,
                $this->year,
                $this->month,
                $category->id,
                $category->name,
            );
        }

        return $sheets;
    }
}
