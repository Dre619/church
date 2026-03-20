<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Giving Statement {{ $year }} — {{ $member->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        @page { margin: 12mm 14mm; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            color: #1a1a2e;
            background: #ffffff;
            line-height: 1.5;
        }

        /* ── Header ─────────────────────────────────────────────── */
        .header {
            background-color: #1e3a5f;
            color: #ffffff;
            padding: 28px 36px 24px;
        }
        .header-org {
            font-size: 13px;
            font-weight: bold;
            opacity: 0.85;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .header-title {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: -0.3px;
            margin-bottom: 4px;
        }
        .header-sub {
            font-size: 11px;
            color: #93c5fd;
        }

        /* ── Accent bar ─────────────────────────────────────────── */
        .accent-bar {
            background-color: #2563eb;
            height: 5px;
        }

        /* ── Meta row ───────────────────────────────────────────── */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .meta-table td {
            padding: 16px 36px;
            vertical-align: top;
        }
        .meta-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #64748b;
            margin-bottom: 3px;
        }
        .meta-value {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
        }

        /* ── Section wrapper ────────────────────────────────────── */
        .section {
            padding: 22px 36px;
        }
        .section-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
        }

        /* ── Summary cards (3-column table) ─────────────────────── */
        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-bottom: 20px;
        }
        .summary-card {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 14px 16px;
            width: 33.33%;
            vertical-align: top;
        }
        .card-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #3b82f6;
            margin-bottom: 5px;
        }
        .card-value {
            font-size: 20px;
            font-weight: bold;
            color: #1e3a5f;
        }

        /* ── Data tables ─────────────────────────────────────────── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 24px;
        }
        .data-table thead th {
            background-color: #f1f5f9;
            padding: 8px 12px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            font-weight: bold;
        }
        .data-table tbody td {
            padding: 9px 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        .data-table tfoot td {
            padding: 9px 12px;
            background-color: #f8fafc;
            border-top: 2px solid #e2e8f0;
            font-weight: bold;
            color: #0f172a;
        }

        .text-right { text-align: right; }
        .amount     { text-align: right; font-weight: bold; color: #065f46; }

        /* ── Stripe alternate rows ───────────────────────────────── */
        .data-table tbody tr.stripe td {
            background-color: #f8fafc;
        }

        /* ── Divider ─────────────────────────────────────────────── */
        .divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 0 36px;
        }

        /* ── Footer ──────────────────────────────────────────────── */
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 14px 36px;
            font-size: 10px;
            color: #94a3b8;
            text-align: center;
        }
        .footer strong {
            color: #64748b;
        }

        /* ── Badge ───────────────────────────────────────────────── */
        .badge {
            display: inline-block;
            background-color: #dcfce7;
            border: 1px solid #86efac;
            color: #15803d;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 8px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
    </style>
</head>
<body>

    {{-- ── Header ─────────────────────────────────────────────── --}}
    <div class="header">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                @if($logoBase64)
                <td style="width:68px; padding-right:0; vertical-align:middle;">
                    <img src="{{ $logoBase64 }}" alt="{{ $organization->name }}"
                         style="width:52px; height:52px; border-radius:50%; border:2px solid rgba(255,255,255,0.3);">
                </td>
                @endif
                <td style="vertical-align:middle;">
                    <div class="header-org">{{ $organization->name }}</div>
                    <div class="header-title">Annual Giving Statement</div>
                    <div class="header-sub">Tax year {{ $year }} &nbsp;&bull;&nbsp; Prepared {{ now()->format('F j, Y') }}</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="accent-bar"></div>

    {{-- ── Member meta ─────────────────────────────────────────── --}}
    <table class="meta-table">
        <tr>
            <td>
                <div class="meta-label">Member Name</div>
                <div class="meta-value">{{ $member->name }}</div>
            </td>
            <td>
                <div class="meta-label">Email Address</div>
                <div class="meta-value">{{ $member->email ?? '—' }}</div>
            </td>
            <td>
                <div class="meta-label">Tax Year</div>
                <div class="meta-value">{{ $year }}</div>
            </td>
            <td style="text-align:right; padding-right:36px;">
                <span class="badge">Official Record</span>
            </td>
        </tr>
    </table>

    {{-- ── Summary cards ───────────────────────────────────────── --}}
    <div class="section">
        <div class="section-title">Summary</div>
        <table class="summary-table">
            <tr>
                <td class="summary-card">
                    <div class="card-label">Total Contributions</div>
                    <div class="card-value">{{ format_currency($totalGiven, $organization->currency ?? 'ZMW') }}</div>
                </td>
                <td class="summary-card">
                    <div class="card-label">Number of Payments</div>
                    <div class="card-value">{{ $payments->count() }}</div>
                </td>
                <td class="summary-card">
                    <div class="card-label">Categories</div>
                    <div class="card-value">{{ $breakdown->count() }}</div>
                </td>
            </tr>
        </table>

        {{-- ── Breakdown by category ───────────────────────────── --}}
        <div class="section-title">Contributions by Category</div>
        <table class="data-table" style="margin-bottom:24px">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="text-right">Payments</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($breakdown as $i => $row)
                    <tr class="{{ $i % 2 === 1 ? 'stripe' : '' }}">
                        <td>{{ $row['category'] }}</td>
                        <td class="text-right">{{ $row['count'] }}</td>
                        <td class="amount">{{ format_currency($row['total'], $organization->currency ?? 'ZMW') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td class="text-right">{{ $payments->count() }}</td>
                    <td class="amount">{{ format_currency($totalGiven, $organization->currency ?? 'ZMW') }}</td>
                </tr>
            </tfoot>
        </table>

        {{-- ── Transaction detail ──────────────────────────────── --}}
        <div class="section-title">Transaction Detail</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Payment Method</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $i => $payment)
                    <tr class="{{ $i % 2 === 1 ? 'stripe' : '' }}">
                        <td>{{ $payment->donation_date?->format('M d, Y') ?? '—' }}</td>
                        <td>{{ $payment->category?->name ?? 'Uncategorised' }}</td>
                        <td style="text-transform:capitalize">{{ str_replace('_', ' ', $payment->payment_method ?? '—') }}</td>
                        <td class="amount">{{ format_currency($payment->amount, $organization->currency ?? 'ZMW') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center; padding:20px; color:#94a3b8;">
                            No contributions recorded for {{ $year }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Footer ──────────────────────────────────────────────── --}}
    <div class="footer">
        <strong>{{ $organization->name }}</strong>
        @if ($organization->email)
            &nbsp;&bull;&nbsp; {{ $organization->email }}
        @endif
        @if ($organization->phone)
            &nbsp;&bull;&nbsp; {{ $organization->phone }}
        @endif
        <br>
        This statement is provided for your records. &nbsp;&bull;&nbsp; Generated {{ now()->format('F j, Y \a\t g:i A') }}
    </div>

</body>
</html>
