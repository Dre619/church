<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Payments;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Spatie\Browsershot\Browsershot;
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

        $html = view('statements.giving', compact(
            'member', 'organization', 'payments', 'totalGiven', 'breakdown', 'year'
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
}
