<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Trading Report - {{ $startDate->format('F Y') }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #1a1a1a;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #22c55e;
            padding-bottom: 20px;
        }
        .header h1 {
            font-size: 24px;
            margin: 0 0 5px 0;
            color: #1a1a1a;
        }
        .header p {
            margin: 0;
            color: #666;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #22c55e;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .stat-box {
            display: table-cell;
            width: 16.66%;
            padding: 10px;
            text-align: center;
            background: #f5f5f5;
            border: 1px solid #ddd;
        }
        .stat-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 16px;
            font-weight: bold;
            margin-top: 5px;
        }
        .profit { color: #22c55e; }
        .loss { color: #ef4444; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f5f5f5;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            color: #666;
        }
        tr:nth-child(even) {
            background: #fafafa;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #999;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Trading Journal Report</h1>
        <p>{{ $startDate->format('F Y') }} | {{ $user->name }}</p>
    </div>

    <div class="section">
        <div class="section-title">Performance Summary</div>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Total Trades</div>
                <div class="stat-value">{{ $stats['total_trades'] }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Win Rate</div>
                <div class="stat-value">{{ $stats['winrate'] }}%</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total P/L</div>
                <div class="stat-value {{ $stats['total_profit'] >= 0 ? 'profit' : 'loss' }}">
                    ${{ number_format($stats['total_profit'], 2) }}
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Profit Factor</div>
                <div class="stat-value">{{ $stats['profit_factor'] }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Avg Win</div>
                <div class="stat-value profit">${{ number_format($stats['average_win'], 2) }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Avg Loss</div>
                <div class="stat-value loss">-${{ number_format(abs($stats['average_loss']), 2) }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Trade Details</div>
        <table>
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Pair</th>
                    <th>Type</th>
                    <th>Lots</th>
                    <th>Open Time</th>
                    <th>Close Time</th>
                    <th>Open Price</th>
                    <th>Close Price</th>
                    <th class="text-right">P/L</th>
                </tr>
            </thead>
            <tbody>
                @foreach($trades as $trade)
                <tr>
                    <td>{{ $trade->ticket }}</td>
                    <td>{{ $trade->pair }}</td>
                    <td>{{ strtoupper($trade->type) }}</td>
                    <td>{{ $trade->lots }}</td>
                    <td>{{ $trade->open_time->format('M d, H:i') }}</td>
                    <td>{{ $trade->close_time ? $trade->close_time->format('M d, H:i') : '-' }}</td>
                    <td>{{ $trade->open_price }}</td>
                    <td>{{ $trade->close_price ?? '-' }}</td>
                    <td class="text-right {{ $trade->profit >= 0 ? 'profit' : 'loss' }}">
                        {{ $trade->profit >= 0 ? '+' : '' }}${{ number_format($trade->profit, 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        Generated by Trading Journal | {{ now()->format('F d, Y H:i:s') }}
    </div>
</body>
</html>

