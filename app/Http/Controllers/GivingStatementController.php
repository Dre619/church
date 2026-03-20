<?php

namespace App\Http\Controllers;

use App\Exports\AllGivingStatementsExport;
use App\Exports\GivingStatementExport;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Payments;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class GivingStatementController extends Controller
{
    public function download(int $userId, int $year): Response
    {
        $orgUser = OrganizationUser::where('user_id', auth()->id())->first();
        abort_unless($orgUser, 403);

        $organization = Organization::findOrFail($orgUser->organization_id);

        $member = User::findOrFail($userId);

        // Verify the member belongs to this org
        abort_unless(
            OrganizationUser::where('user_id', $userId)
                ->where('organization_id', $organization->id)
                ->exists(),
            403,
        );

        $payments = Payments::with('category')
            ->where('organization_id', $organization->id)
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhere('name', $member->name))
            ->whereYear('donation_date', $year)
            ->orderBy('donation_date')
            ->get();

        $totalGiven = $payments->sum('amount');
        $breakdown  = $payments->groupBy(fn ($p) => $p->category?->name ?? 'Uncategorised')
            ->map(fn ($group, $cat) => [
                'category' => $cat,
                'count'    => $group->count(),
                'total'    => $group->sum('amount'),
            ])->values();

        $logoBase64 = null;

        if ($organization->logo && Storage::disk('public')->exists($organization->logo)) {
            $logoData   = Storage::disk('public')->get($organization->logo);
            $logoMime   = Storage::disk('public')->mimeType($organization->logo);
            $logoBase64 = 'data:' . $logoMime . ';base64,' . base64_encode($logoData);
        }

        $html = view('statements.giving', compact(
            'member', 'organization', 'payments', 'totalGiven', 'breakdown', 'year', 'logoBase64'
        ))->render();

        $filename = "giving-statement-{$member->name}-{$year}.pdf";

        try {
            $pdf = Browsershot::html($html)->format('A4')->margins(10, 10, 10, 10)->pdf();

            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"{$filename}\"",
            ]);
        } catch (Throwable) {
            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

            return $pdf->stream($filename);
        }
    }

    public function downloadExcel(int $userId, int $year): BinaryFileResponse
    {
        $orgUser = OrganizationUser::where('user_id', auth()->id())->first();
        abort_unless($orgUser, 403);

        $organization = Organization::findOrFail($orgUser->organization_id);
        $member       = User::findOrFail($userId);

        abort_unless(
            OrganizationUser::where('user_id', $userId)
                ->where('organization_id', $organization->id)
                ->exists(),
            403,
        );

        $filename = "giving-statement-{$member->name}-{$year}.xlsx";

        return Excel::download(
            new GivingStatementExport(
                $organization->id,
                $member->id,
                $member->name,
                $member->email ?? '',
                $year,
                $organization->currency ?? 'ZMW',
            ),
            $filename,
        );
    }

    public function downloadAllExcel(int $year): BinaryFileResponse
    {
        $orgUser = OrganizationUser::where('user_id', auth()->id())->first();
        abort_unless($orgUser, 403);

        $organization = Organization::findOrFail($orgUser->organization_id);

        $filename = "giving-statements-all-{$year}.xlsx";

        return Excel::download(
            new AllGivingStatementsExport(
                $organization->id,
                $year,
                $organization->currency ?? 'ZMW',
            ),
            $filename,
        );
    }
}
