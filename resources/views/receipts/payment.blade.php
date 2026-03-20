<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #{{ str_pad($payment->id, 8, '0', STR_PAD_LEFT) }} — {{ $organization->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        @page { margin: 10mm 12mm; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            background: #ffffff;
            color: #1a1a2e;
            font-size: 12px;
            line-height: 1.5;
        }

        /* ── Header ──────────────────────────────────────────────── */
        .header {
            background-color: #1e3a5f;
            color: #ffffff;
            padding: 0;
        }
        .header-inner {
            width: 100%;
            border-collapse: collapse;
        }
        .header-inner td {
            padding: 26px 36px;
            vertical-align: middle;
        }
        .header-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.3);
        }
        .header-logo-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.3);
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            color: #ffffff;
            line-height: 56px;
        }
        .org-name    { font-size: 18px; font-weight: bold; letter-spacing: -0.3px; }
        .org-contact { font-size: 11px; color: rgba(255,255,255,0.7); margin-top: 3px; }
        .receipt-number-cell { text-align: right; white-space: nowrap; }
        .receipt-lbl { font-size: 10px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,0.55); }
        .receipt-num { font-size: 22px; font-weight: bold; letter-spacing: -0.5px; }

        /* ── Accent bar ───────────────────────────────────────────── */
        .accent-bar { background-color: #2563eb; height: 5px; }

        /* ── Body ────────────────────────────────────────────────── */
        .body { padding: 28px 36px; }

        /* Status / date row */
        .status-table { width: 100%; border-collapse: collapse; margin-bottom: 22px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 18px; }
        .status-table td { padding-bottom: 18px; vertical-align: middle; }
        .badge {
            display: inline-block;
            background-color: #dcfce7;
            border: 1px solid #86efac;
            color: #15803d;
            font-size: 10px;
            font-weight: bold;
            padding: 3px 10px;
            letter-spacing: 0.05em;
        }
        .issue-date { font-size: 11px; color: #6b7280; text-align: right; }

        /* Section title */
        .section-title {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .donor-name { font-size: 16px; font-weight: bold; color: #0f172a; }
        .donor-meta { font-size: 11px; color: #6b7280; margin-top: 2px; }

        /* Divider */
        .divider { height: 1px; background-color: #f1f5f9; margin: 20px 0; }

        /* Amount box */
        .amount-box {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
        }
        .amount-box td {
            padding: 16px 20px;
            vertical-align: middle;
            background-color: #eff6ff;
        }
        .amount-label { font-size: 12px; font-weight: bold; color: #1d4ed8; }
        .amount-value { font-size: 26px; font-weight: bold; color: #1d4ed8; letter-spacing: -0.5px; text-align: right; }

        /* Details grid (2-col table) */
        .details-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-bottom: 14px;
            margin-left: -8px;
            margin-right: -8px;
        }
        .details-table td {
            width: 50%;
            padding: 12px 16px;
            vertical-align: top;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .detail-label {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .detail-value { font-size: 13px; color: #0f172a; font-weight: bold; }

        /* Pledge box */
        .pledge-box {
            background-color: #f5f3ff;
            border: 1px solid #ddd6fe;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        .pledge-title { font-size: 9px; font-weight: bold; color: #7c3aed; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
        .pledge-detail { font-size: 11px; color: #4c1d95; }

        /* Notes box */
        .notes-box {
            background-color: #fffbeb;
            border-left: 3px solid #f59e0b;
            padding: 10px 14px;
            margin-bottom: 20px;
        }
        .notes-box p { font-size: 11px; color: #78350f; }

        /* Confirmation note */
        .confirm-note {
            font-size: 10px;
            color: #94a3b8;
            text-align: center;
            margin-top: 8px;
        }

        /* Footer */
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            width: 100%;
            border-collapse: collapse;
        }
        .footer td {
            padding: 14px 36px;
            font-size: 10px;
            color: #94a3b8;
            vertical-align: middle;
        }
        .footer-brand { text-align: right; font-weight: bold; color: #64748b; }
    </style>
</head>
<body>

    {{-- ── Header ────────────────────────────────────────────────── --}}
    <div class="header">
        <table class="header-inner">
            <tr>
                <td style="width:76px; padding-right:0;">
                    @if($logoBase64)
                        <img src="{{ $logoBase64 }}" alt="{{ $organization->name }}" class="header-logo">
                    @else
                        <div class="header-logo-placeholder">{{ strtoupper(substr($organization->name, 0, 1)) }}</div>
                    @endif
                </td>
                <td>
                    <div class="org-name">{{ $organization->name }}</div>
                    <div class="org-contact">
                        @if($organization->address){{ $organization->address }}@endif
                        @if($organization->phone) &bull; {{ $organization->phone }}@endif
                        @if($organization->email) &bull; {{ $organization->email }}@endif
                    </div>
                </td>
                <td class="receipt-number-cell">
                    <div class="receipt-lbl">Receipt</div>
                    <div class="receipt-num">#{{ str_pad($payment->id, 8, '0', STR_PAD_LEFT) }}</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="accent-bar"></div>

    {{-- ── Body ─────────────────────────────────────────────────── --}}
    <div class="body">

        {{-- Status & date --}}
        <table class="status-table">
            <tr>
                <td><span class="badge">Payment Confirmed</span></td>
                <td class="issue-date">Issued: {{ now()->format('F j, Y') }}</td>
            </tr>
        </table>

        {{-- Donor --}}
        <div class="section-title">Received From</div>
        <div class="donor-name">{{ $payment->user?->name ?? $payment->name ?? 'Walk-in Donor' }}</div>
        @if($payment->user?->email)
            <div class="donor-meta">{{ $payment->user->email }}</div>
        @endif
        @if($payment->transaction_id)
            <div class="donor-meta">Ref: {{ $payment->transaction_id }}</div>
        @endif

        <div class="divider"></div>

        {{-- Amount --}}
        <table class="amount-box">
            <tr>
                <td class="amount-label">Amount Received</td>
                <td class="amount-value">{{ format_currency($payment->amount, $organization->currency ?? 'ZMW') }}</td>
            </tr>
        </table>

        {{-- Details grid --}}
        <table class="details-table">
            <tr>
                <td>
                    <div class="detail-label">Category</div>
                    <div class="detail-value">{{ $payment->category?->name ?? '—' }}</div>
                </td>
                <td>
                    <div class="detail-label">Payment Date</div>
                    <div class="detail-value">{{ \Carbon\Carbon::parse($payment->donation_date)->format('F j, Y') }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="detail-label">Payment Method</div>
                    <div class="detail-value">{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</div>
                </td>
                <td>
                    <div class="detail-label">Receipt Number</div>
                    <div class="detail-value">#{{ str_pad($payment->id, 8, '0', STR_PAD_LEFT) }}</div>
                </td>
            </tr>
        </table>

        {{-- Pledge info --}}
        @if($payment->pledge)
            <div class="pledge-box">
                <div class="pledge-title">Linked to Pledge</div>
                <div class="pledge-detail">
                    Project: {{ $payment->pledge->project?->project_title ?? '—' }}
                    &nbsp;&bull;&nbsp;
                    Total Pledged: {{ format_currency($payment->pledge->amount, $organization->currency ?? 'ZMW') }}
                    &nbsp;&bull;&nbsp;
                    Fulfilled: {{ format_currency($payment->pledge->fulfilled_amount, $organization->currency ?? 'ZMW') }}
                </div>
            </div>
        @endif

        {{-- Notes --}}
        @if($payment->other)
            <div class="notes-box">
                <p><strong>Notes:</strong> {{ $payment->other }}</p>
            </div>
        @endif

        <p class="confirm-note">
            This receipt confirms that the above payment has been received by {{ $organization->name }}.
            @if($organization->website) Thank you for your generous contribution. Visit us at {{ $organization->website }}.@endif
        </p>

    </div>

    {{-- ── Footer ───────────────────────────────────────────────── --}}
    <table class="footer">
        <tr>
            <td>{{ $organization->name }} &copy; {{ now()->year }}</td>
            <td class="footer-brand">The Treasurer &mdash; Church Management</td>
        </tr>
    </table>

</body>
</html>
