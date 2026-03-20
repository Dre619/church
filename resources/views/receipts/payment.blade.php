<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #{{ str_pad($payment->id, 8, '0', STR_PAD_LEFT) }} — {{ $organization->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            background: #f4f4f4;
            color: #1a1a1a;
            font-size: 14px;
            line-height: 1.5;
        }

        .page {
            max-width: 680px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
        }

        /* ── Header ── */
        .header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
            color: #fff;
            padding: 32px 40px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-logo {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.3);
            flex-shrink: 0;
        }

        .header-logo-placeholder {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            color: rgba(255,255,255,0.9);
            flex-shrink: 0;
        }

        .header-info { flex: 1; }
        .org-name { font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
        .org-contact { font-size: 12px; color: rgba(255,255,255,0.75); margin-top: 4px; }
        .receipt-label {
            text-align: right;
            flex-shrink: 0;
        }
        .receipt-label .label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.6);
        }
        .receipt-label .number {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        /* ── Body ── */
        .body { padding: 36px 40px; }

        /* Status badge */
        .status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #e5e7eb;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 600;
            background: #dcfce7;
            color: #15803d;
        }

        .badge::before {
            content: '';
            display: block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #16a34a;
        }

        .issue-date { font-size: 13px; color: #6b7280; }

        /* Donor section */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 8px;
        }

        .donor-name { font-size: 18px; font-weight: 600; color: #111827; }
        .donor-meta { font-size: 13px; color: #6b7280; margin-top: 2px; }

        /* Divider */
        .divider { height: 1px; background: #f3f4f6; margin: 24px 0; }

        /* Details grid */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }

        .detail-item {}
        .detail-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 3px;
        }
        .detail-value { font-size: 14px; color: #111827; font-weight: 500; }

        /* Amount highlight */
        .amount-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .amount-label { font-size: 13px; font-weight: 600; color: #0369a1; }
        .amount-value { font-size: 28px; font-weight: 800; color: #0369a1; letter-spacing: -0.5px; }

        /* Notes */
        .notes-box {
            background: #fffbeb;
            border-left: 3px solid #f59e0b;
            padding: 12px 16px;
            border-radius: 0 6px 6px 0;
            margin-bottom: 24px;
        }
        .notes-box p { font-size: 13px; color: #78350f; }

        /* Pledge info */
        .pledge-box {
            background: #f5f3ff;
            border: 1px solid #ddd6fe;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 24px;
        }
        .pledge-box .pledge-title { font-size: 12px; font-weight: 700; color: #7c3aed; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .pledge-box .pledge-detail { font-size: 13px; color: #4c1d95; }

        /* Footer */
        .footer {
            background: #f9fafb;
            border-top: 1px solid #f3f4f6;
            padding: 20px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .footer-text { font-size: 12px; color: #9ca3af; }
        .footer-brand { font-size: 12px; font-weight: 600; color: #6b7280; }

        @page { margin: 10mm; }
    </style>
</head>
<body>

    <div class="page">

        {{-- ── Header ── --}}
        <div class="header">
            @if($organization->logo)
                <img src="{{ Storage::url($organization->logo) }}" alt="{{ $organization->name }}" class="header-logo">
            @else
                <div class="header-logo-placeholder">{{ strtoupper(substr($organization->name, 0, 1)) }}</div>
            @endif

            <div class="header-info">
                <div class="org-name">{{ $organization->name }}</div>
                <div class="org-contact">
                    @if($organization->address){{ $organization->address }}@endif
                    @if($organization->phone) · {{ $organization->phone }}@endif
                    @if($organization->email) · {{ $organization->email }}@endif
                </div>
            </div>

            <div class="receipt-label">
                <div class="label">Receipt</div>
                <div class="number">#{{ str_pad($payment->id, 8, '0', STR_PAD_LEFT) }}</div>
            </div>
        </div>

        {{-- ── Body ── --}}
        <div class="body">

            {{-- Status & Date --}}
            <div class="status-row">
                <span class="badge">Payment Confirmed</span>
                <span class="issue-date">
                    Issued: {{ now()->format('F j, Y') }}
                </span>
            </div>

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
            <div class="amount-box">
                <div class="amount-label">Amount Received</div>
                <div class="amount-value">{{ format_currency($payment->amount, $organization->currency ?? 'NGN') }}</div>
            </div>

            {{-- Details Grid --}}
            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-label">Category</div>
                    <div class="detail-value">{{ $payment->category?->name ?? '—' }}</div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Payment Date</div>
                    <div class="detail-value">
                        {{ \Carbon\Carbon::parse($payment->donation_date)->format('F j, Y') }}
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Payment Method</div>
                    <div class="detail-value">{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Receipt Number</div>
                    <div class="detail-value">#{{ str_pad($payment->id, 8, '0', STR_PAD_LEFT) }}</div>
                </div>
            </div>

            {{-- Pledge Info (if linked) --}}
            @if($payment->pledge)
                <div class="pledge-box">
                    <div class="pledge-title">Linked to Pledge</div>
                    <div class="pledge-detail">
                        Project: {{ $payment->pledge->project?->project_title ?? '—' }}
                        &nbsp;·&nbsp;
                        Total Pledged: {{ format_currency($payment->pledge->amount, $organization->currency ?? 'NGN') }}
                        &nbsp;·&nbsp;
                        Fulfilled: {{ format_currency($payment->pledge->fulfilled_amount, $organization->currency ?? 'NGN') }}
                    </div>
                </div>
            @endif

            {{-- Notes --}}
            @if($payment->other)
                <div class="notes-box">
                    <p><strong>Notes:</strong> {{ $payment->other }}</p>
                </div>
            @endif

            <p style="font-size:12px;color:#9ca3af;text-align:center;margin-top:8px;">
                This receipt confirms that the above payment has been received by {{ $organization->name }}.
                @if($organization->website)Thank you for your generous contribution. Visit us at {{ $organization->website }}.@endif
            </p>

        </div>

        {{-- ── Footer ── --}}
        <div class="footer">
            <div class="footer-text">{{ $organization->name }} &copy; {{ now()->year }}</div>
            <div class="footer-brand">The Treasurer &mdash; Church Management</div>
        </div>

    </div>

</body>
</html>
