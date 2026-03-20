<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Payments;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Spatie\Browsershot\Browsershot;
use Throwable;

class PaymentReceiptController extends Controller
{
    public function show(Payments $payment): Response
    {
        $orgUser = OrganizationUser::where('user_id', auth()->id())->first();

        abort_unless(
            $orgUser && $orgUser->organization_id === $payment->organization_id,
            403,
        );

        $organization = Organization::findOrFail($payment->organization_id);

        $payment->load(['user', 'category', 'pledge.project']);

        $html = view('receipts.payment', compact('payment', 'organization'))->render();

        $filename = "receipt-{$payment->id}.pdf";

        try {
            $pdf = Browsershot::html($html)
                ->format('A4')
                ->margins(10, 10, 10, 10)
                ->pdf();

            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"{$filename}\"",
            ]);
        } catch (Throwable) {
            // Fall back to dompdf if Browsershot/Chrome is unavailable
            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

            return $pdf->stream($filename);
        }
    }
}
