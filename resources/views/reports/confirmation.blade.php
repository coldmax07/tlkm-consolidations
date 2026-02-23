@php
    function money_fmt($value) {
        $num = (float) $value;
        $formatted = number_format(abs($num), 2, '.', ' ');
        $prefix = 'R ';
        return $num < 0 ? "({$prefix}{$formatted})" : "{$prefix}{$formatted}";
    }
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 6mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #333; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .meta-title { font-size: 16px; font-weight: bold; text-align: center; }
        .bold { font-weight: bold; }
        .group-header-current { background: #00A1D6; color: #fff; text-align: center; font-weight: bold; }
        .group-header-counter { background: #008A00; color: #fff; text-align: center; font-weight: bold; }
        .subheader-current { background: #E6F2FF; font-weight: bold; text-align: center; }
        .subheader-counter { background: #E5F4EA; font-weight: bold; text-align: center; }
        .td-border, .th-border { border: 1px solid #ccc; padding: 2px; }
        .agree-row { background: #EBF7E6; }
        .zebra-row { background: #F5FBFF; }
        .current-cell { background: #F8FBFF; }
        .counter-cell { background: #F1FAF4; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .variance-red { color: #c00; font-weight: bold; }
        .variance-green { color: #008000; font-weight: bold; }
        .nowrap { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    </style>
</head>
<body>
    <table>
        <tr><td class="meta-title nowrap" colspan="17">{{ $reportTitle }}</td></tr>
        <tr><td class="bold nowrap">Company: {{ $companyName }}</td></tr>
        <tr><td class="bold nowrap">Period: {{ $period }}</td></tr>
        <tr><td class="bold nowrap">Generated: {{ $generatedAt }}</td></tr>
        <tr><td colspan="17" style="height:4px;"></td></tr>
    </table>

    <table>
        <colgroup>
            <col style="width:12%;">
            <col style="width:12%;">
            <col style="width:10%;">
            <col style="width:8%;">
            <col style="width:8%;">
            <col style="width:10%;">
            <col style="width:10%;">
            <col style="width:7%;">
            <col style="width:7%;">
            <col style="width:6%;">
            <col style="width:6%;">
            <col style="width:6%;">
            <col style="width:6%;">
            <col style="width:6%;">
            <col style="width:6%;">
            <col style="width:6%;">
            <col style="width:6%;">
        </colgroup>
        <tr>
            <th class="th-border" colspan="9"></th>
            <th class="th-border group-header-current" colspan="4">CURRENT COMPANY</th>
            <th class="th-border group-header-counter" colspan="4">COUNTER-PART COMPANY</th>
        </tr>
        <tr>
            <th class="th-border subheader-current">HFM ACCOUNT</th>
            <th class="th-border subheader-current">TRADING PARTNER</th>
            <th class="th-border subheader-current">DESCRIPTION</th>
            <th class="th-border subheader-current">ADJUSTMENT (SENDER)</th>
            <th class="th-border subheader-current">FINAL AMOUNT (SENDER)</th>
            <th class="th-border subheader-current">CURRENT COMPANY AMOUNT</th>
            <th class="th-border subheader-current">COUNTERPARTY AMOUNT</th>
            <th class="th-border subheader-current">VARIANCE</th>
            <th class="th-border subheader-current">AGREEMENT</th>
            <th class="th-border subheader-current">PREPARED BY</th>
            <th class="th-border subheader-current">PREPARED AT</th>
            <th class="th-border subheader-current">REVIEWED BY</th>
            <th class="th-border subheader-current">REVIEWED AT</th>
            <th class="th-border subheader-counter">PREPARED BY</th>
            <th class="th-border subheader-counter">PREPARED AT</th>
            <th class="th-border subheader-counter">REVIEWED BY</th>
            <th class="th-border subheader-counter">REVIEWED AT</th>
        </tr>
        @foreach ($rows as $idx => $row)
            @php
                $isAgree = strtolower($row['agreement'] ?? '') === 'agree';
                $rowClass = $isAgree ? 'agree-row' : (($idx % 2 === 0) ? 'zebra-row' : '');
                $variance = (float) ($row['variance'] ?? 0);
                $varianceClass = $variance > 0 ? 'variance-green' : ($variance < 0 ? 'variance-red' : '');
            @endphp
            <tr class="{{ $rowClass }}">
                <td class="td-border nowrap">{{ $row['hfm_account'] ?? '—' }}</td>
                <td class="td-border nowrap">{{ $row['trading_partner'] ?? '—' }}</td>
                <td class="td-border nowrap">{{ $row['description'] ?? '—' }}</td>
                <td class="td-border text-right nowrap">{{ $row['adjustment_amount'] !== null ? money_fmt($row['adjustment_amount']) : '—' }}</td>
                <td class="td-border text-right nowrap">{{ $row['final_amount'] !== null ? money_fmt($row['final_amount']) : '—' }}</td>
                <td class="td-border current-cell text-right nowrap">{{ money_fmt($row['current_amount'] ?? 0) }}</td>
                <td class="td-border current-cell text-right nowrap">{{ money_fmt($row['counterparty_amount'] ?? 0) }}</td>
                <td class="td-border text-right nowrap {{ $varianceClass }}">{{ money_fmt($variance) }}</td>
                <td class="td-border text-center nowrap">{{ $row['agreement'] ?? '—' }}</td>
                <td class="td-border current-cell text-center nowrap">{{ $row['prepared_by'] ?? '—' }}</td>
                <td class="td-border current-cell text-center nowrap">{{ $row['prepared_at'] ?? '—' }}</td>
                <td class="td-border current-cell text-center nowrap">{{ $row['reviewed_by'] ?? '—' }}</td>
                <td class="td-border current-cell text-center nowrap">{{ $row['reviewed_at'] ?? '—' }}</td>
                <td class="td-border counter-cell text-center nowrap">{{ $row['counter_prepared_by'] ?? '—' }}</td>
                <td class="td-border counter-cell text-center nowrap">{{ $row['counter_prepared_at'] ?? '—' }}</td>
                <td class="td-border counter-cell text-center nowrap">{{ $row['counter_reviewed_by'] ?? '—' }}</td>
                <td class="td-border counter-cell text-center nowrap">{{ $row['counter_reviewed_at'] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
