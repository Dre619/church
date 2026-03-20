<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Giving Statement {{ $year }} — {{ $member->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        @page { margin: 10mm; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #1a1a1a; background: #fff; }
        .page { max-width: 720px; margin: 0 auto; }

        .header { background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); color: #fff; padding: 32px 40px; }
        .header h1 { font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
        .header p  { opacity: 0.8; font-size: 13px; margin-top: 4px; }
        .header .org { font-size: 15px; font-weight: 600; margin-bottom: 8px; opacity: 0.9; }

        .meta { display: flex; gap: 32px; padding: 24px 40px; border-bottom: 1px solid #e5e7eb; background: #f9fafb; }
        .meta-item label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; display: block; margin-bottom: 2px; }
        .meta-item span  { font-size: 14px; font-weight: 600; color: #111827; }

        .section { padding: 24px 40px; }
        .section h2 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-bottom: 14px; }

        .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 28px; }
        .summary-card { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 14px 16px; }
        .summary-card .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #0369a1; margin-bottom: 4px; }
        .summary-card .value { font-size: 20px; font-weight: 700; color: #0c4a6e; }

        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        thead th { background: #f3f4f6; padding: 8px 12px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        tbody td { padding: 9px 12px; border-bottom: 1px solid #f3f4f6; color: #374151; }
        tbody tr:last-child td { border-bottom: none; }
        .amount { text-align: right; font-weight: 600; color: #065f46; }

        .breakdown-table { margin-bottom: 24px; }
        .breakdown-table tfoot td { font-weight: 700; background: #f9fafb; border-top: 2px solid #e5e7eb; }

        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 18px 40px; font-size: 11px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="header">
        <div class="org">{{ $organization->name }}</div>
        <h1>Annual Giving Statement</h1>
        <p>Tax year {{ $year }} &nbsp;·&nbsp; Prepared {{ now()->format('F j, Y') }}</p>
    </div>

    {{-- Member meta --}}
    <div class="meta">
        <div class="meta-item">
            <label>Member Name</label>
            <span>{{ $member->name }}</span>
        </div>
        <div class="meta-item">
            <label>Email</label>
            <span>{{ $member->email }}</span>
        </div>
        <div class="meta-item">
            <label>Tax Year</label>
            <span>{{ $year }}</span>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="section">
        <h2>Summary</h2>
        <div class="summary">
            <div class="summary-card">
                <div class="label">Total Contributions</div>
                <div class="value">{{ format_currency($totalGiven, $organization->currency ?? 'ZMW') }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Number of Payments</div>
                <div class="value">{{ $payments->count() }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Categories</div>
                <div class="value">{{ $breakdown->count() }}</div>
            </div>
        </div>

        {{-- Breakdown by category --}}
        <h2>By Category</h2>
        <table class="breakdown-table" style="margin-bottom:24px">
            <thead>
                <tr>
                    <th>Category</th>
                    <th style="text-align:right">Payments</th>
                    <th style="text-align:right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($breakdown as $row)
                    <tr>
                        <td>{{ $row['category'] }}</td>
                        <td style="text-align:right">{{ $row['count'] }}</td>
                        <td class="amount">{{ format_currency($row['total'], $organization->currency ?? 'ZMW') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Total</strong></td>
                    <td style="text-align:right"><strong>{{ $payments->count() }}</strong></td>
                    <td class="amount"><strong>{{ format_currency($totalGiven, $organization->currency ?? 'ZMW') }}</strong></td>
                </tr>
            </tfoot>
        </table>

        {{-- Detailed transaction list --}}
        <h2>Transaction Detail</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Method</th>
                    <th style="text-align:right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td>{{ $payment->donation_date?->format('M d, Y') ?? '—' }}</td>
                        <td>{{ $payment->category?->name ?? 'Uncategorised' }}</td>
                        <td style="text-transform:capitalize">{{ str_replace('_', ' ', $payment->payment_method ?? '—') }}</td>
                        <td class="amount">{{ format_currency($payment->amount, $organization->currency ?? 'ZMW') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;padding:20px;color:#9ca3af">No contributions recorded for {{ $year }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">
        This statement is provided for your records. &nbsp;|&nbsp; {{ $organization->name }}
        @if ($organization->email) &nbsp;·&nbsp; {{ $organization->email }} @endif
    </div>

</div>
</body>
</html>
