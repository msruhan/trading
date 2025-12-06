<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Trade;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    /**
     * Get monthly report data.
     */
    public function monthly(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'nullable|exists:accounts,id',
            'year' => 'nullable|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $user = $request->user();
        $year = $validated['year'] ?? now()->year;
        $month = $validated['month'] ?? now()->month;

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get account IDs
        if (isset($validated['account_id'])) {
            $account = Account::findOrFail($validated['account_id']);
            $this->authorize('view', $account);
            $accountIds = [$account->id];
        } else {
            $accountIds = $user->accounts()->pluck('id');
        }

        // Get trades for the month
        $trades = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->whereBetween('close_time', [$startDate, $endDate])
            ->with('account:id,name,broker_name')
            ->orderBy('close_time')
            ->get();

        // Calculate statistics
        $stats = $this->calculateStats($trades);

        // Group by day
        $dailyData = $trades->groupBy(fn($trade) => $trade->close_time->format('Y-m-d'))
            ->map(function ($dayTrades) {
                return [
                    'trades' => $dayTrades->count(),
                    'profit' => round($dayTrades->sum('profit'), 2),
                    'wins' => $dayTrades->where('profit', '>', 0)->count(),
                    'losses' => $dayTrades->where('profit', '<', 0)->count(),
                ];
            });

        // Group by pair
        $pairData = $trades->groupBy('pair')
            ->map(function ($pairTrades) {
                return [
                    'trades' => $pairTrades->count(),
                    'profit' => round($pairTrades->sum('profit'), 2),
                    'winrate' => $pairTrades->count() > 0 
                        ? round(($pairTrades->where('profit', '>', 0)->count() / $pairTrades->count()) * 100, 1)
                        : 0,
                ];
            })
            ->sortByDesc('profit');

        return response()->json([
            'period' => [
                'year' => $year,
                'month' => $month,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'stats' => $stats,
            'daily_data' => $dailyData,
            'pair_data' => $pairData,
            'trades' => $trades,
        ]);
    }

    /**
     * Export monthly report as CSV.
     */
    public function exportCSV(Request $request): Response
    {
        $validated = $request->validate([
            'account_id' => 'nullable|exists:accounts,id',
            'year' => 'nullable|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $user = $request->user();
        $year = $validated['year'] ?? now()->year;
        $month = $validated['month'] ?? now()->month;

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get account IDs
        if (isset($validated['account_id'])) {
            $account = Account::findOrFail($validated['account_id']);
            $this->authorize('view', $account);
            $accountIds = [$account->id];
        } else {
            $accountIds = $user->accounts()->pluck('id');
        }

        $trades = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->whereBetween('close_time', [$startDate, $endDate])
            ->with('account:id,name,broker_name')
            ->orderBy('close_time')
            ->get();

        // Build CSV
        $headers = [
            'Ticket',
            'Account',
            'Pair',
            'Type',
            'Lots',
            'Open Time',
            'Close Time',
            'Open Price',
            'Close Price',
            'Profit',
            'Swap',
            'Commission',
            'Pips',
            'Duration',
            'Comment',
        ];

        $rows = $trades->map(function ($trade) {
            return [
                $trade->ticket,
                $trade->account->name ?? $trade->account->broker_name,
                $trade->pair,
                $trade->type,
                $trade->lots,
                $trade->open_time->format('Y-m-d H:i:s'),
                $trade->close_time?->format('Y-m-d H:i:s'),
                $trade->open_price,
                $trade->close_price,
                $trade->profit,
                $trade->swap,
                $trade->commission,
                $trade->pips,
                $trade->formatted_duration,
                $trade->comment,
            ];
        });

        $csv = implode(',', $headers) . "\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(function ($value) {
                // Escape values with commas or quotes
                if (str_contains($value ?? '', ',') || str_contains($value ?? '', '"')) {
                    return '"' . str_replace('"', '""', $value) . '"';
                }
                return $value ?? '';
            }, $row)) . "\n";
        }

        $filename = "trading-report-{$year}-{$month}.csv";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export monthly report as PDF.
     */
    public function exportPDF(Request $request): Response
    {
        $validated = $request->validate([
            'account_id' => 'nullable|exists:accounts,id',
            'year' => 'nullable|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $user = $request->user();
        $year = $validated['year'] ?? now()->year;
        $month = $validated['month'] ?? now()->month;

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get account IDs
        if (isset($validated['account_id'])) {
            $account = Account::findOrFail($validated['account_id']);
            $this->authorize('view', $account);
            $accountIds = [$account->id];
        } else {
            $accountIds = $user->accounts()->pluck('id');
        }

        $trades = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->whereBetween('close_time', [$startDate, $endDate])
            ->with('account:id,name,broker_name')
            ->orderBy('close_time')
            ->get();

        $stats = $this->calculateStats($trades);

        $pdf = Pdf::loadView('reports.monthly', [
            'user' => $user,
            'year' => $year,
            'month' => $month,
            'trades' => $trades,
            'stats' => $stats,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        $filename = "trading-report-{$year}-{$month}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Calculate trading statistics.
     */
    protected function calculateStats($trades): array
    {
        $totalTrades = $trades->count();
        $winningTrades = $trades->where('profit', '>', 0)->count();
        $losingTrades = $trades->where('profit', '<', 0)->count();
        $breakeven = $trades->where('profit', 0)->count();

        $totalProfit = $trades->sum('profit');
        $grossProfit = $trades->where('profit', '>', 0)->sum('profit');
        $grossLoss = abs($trades->where('profit', '<', 0)->sum('profit'));

        $totalSwap = $trades->sum('swap');
        $totalCommission = $trades->sum('commission');

        $avgWin = $winningTrades > 0 ? $grossProfit / $winningTrades : 0;
        $avgLoss = $losingTrades > 0 ? $grossLoss / $losingTrades : 0;

        $maxWin = $trades->max('profit') ?? 0;
        $maxLoss = $trades->min('profit') ?? 0;

        $totalLots = $trades->sum('lots');
        $avgLots = $totalTrades > 0 ? $totalLots / $totalTrades : 0;

        return [
            'total_trades' => $totalTrades,
            'winning_trades' => $winningTrades,
            'losing_trades' => $losingTrades,
            'breakeven' => $breakeven,
            'winrate' => $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 2) : 0,
            'total_profit' => round($totalProfit, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_loss' => round($grossLoss, 2),
            'net_profit' => round($totalProfit + $totalSwap - abs($totalCommission), 2),
            'total_swap' => round($totalSwap, 2),
            'total_commission' => round($totalCommission, 2),
            'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : 0,
            'average_win' => round($avgWin, 2),
            'average_loss' => round($avgLoss, 2),
            'max_win' => round($maxWin, 2),
            'max_loss' => round($maxLoss, 2),
            'expectancy' => $totalTrades > 0 ? round($totalProfit / $totalTrades, 2) : 0,
            'total_lots' => round($totalLots, 2),
            'average_lots' => round($avgLots, 4),
        ];
    }
}

