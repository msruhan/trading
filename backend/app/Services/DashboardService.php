<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Balance;
use App\Models\Trade;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get dashboard metrics for a user.
     */
    public function getMetrics(User $user, int $days = 30): array
    {
        $accounts = $user->accounts()->with('latestBalance')->get();
        $accountIds = $accounts->pluck('id');

        // Current balance and equity
        $totalBalance = 0;
        $totalEquity = 0;
        $totalFloatingPL = 0;

        foreach ($accounts as $account) {
            if ($account->latestBalance) {
                $totalBalance += $account->latestBalance->balance;
                $totalEquity += $account->latestBalance->equity;
                $totalFloatingPL += $account->latestBalance->floating_pl;
            }
        }

        // Daily P/L
        $dailyPL = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->whereDate('close_time', today())
            ->sum('profit');

        // Monthly P/L
        $monthlyPL = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->whereMonth('close_time', now()->month)
            ->whereYear('close_time', now()->year)
            ->sum('profit');

        // Win rate (last 30 days)
        $recentTrades = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->where('close_time', '>=', now()->subDays($days))
            ->get();

        $totalTrades = $recentTrades->count();
        $winningTrades = $recentTrades->where('profit', '>', 0)->count();
        $winrate = $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 2) : 0;

        // Open trades count
        $openTrades = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'open')
            ->count();

        return [
            'balance' => round($totalBalance, 2),
            'equity' => round($totalEquity, 2),
            'floating_pl' => round($totalFloatingPL, 2),
            'daily_pl' => round($dailyPL, 2),
            'monthly_pl' => round($monthlyPL, 2),
            'winrate' => $winrate,
            'total_trades' => $totalTrades,
            'winning_trades' => $winningTrades,
            'open_trades' => $openTrades,
            'accounts_count' => $accounts->count(),
        ];
    }

    /**
     * Get equity curve data.
     */
    public function getEquityCurve(User $user, int $days = 30): array
    {
        $accountIds = $user->accounts()->pluck('id');
        $startDate = now()->subDays($days)->startOfDay();

        // Get daily equity snapshots
        $data = Balance::whereIn('account_id', $accountIds)
            ->where('timestamp', '>=', $startDate)
            ->selectRaw('DATE(timestamp) as date, SUM(equity) as equity, SUM(balance) as balance')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill missing dates
        $result = [];
        $currentDate = $startDate->copy();
        $lastKnown = ['equity' => 0, 'balance' => 0];

        while ($currentDate <= now()) {
            $dateStr = $currentDate->format('Y-m-d');
            $found = $data->firstWhere('date', $dateStr);

            if ($found) {
                $lastKnown = [
                    'equity' => (float) $found->equity,
                    'balance' => (float) $found->balance,
                ];
            }

            $result[] = [
                'date' => $dateStr,
                'equity' => $lastKnown['equity'],
                'balance' => $lastKnown['balance'],
            ];

            $currentDate->addDay();
        }

        return $result;
    }

    /**
     * Get daily P/L bar chart data.
     */
    public function getDailyPnL(User $user, int $days = 30): array
    {
        $accountIds = $user->accounts()->pluck('id');
        $startDate = now()->subDays($days)->startOfDay();

        $data = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->selectRaw('DATE(close_time) as date, SUM(profit) as profit, COUNT(*) as trades')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill missing dates
        $result = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= now()) {
            $dateStr = $currentDate->format('Y-m-d');
            $found = $data->firstWhere('date', $dateStr);

            $result[] = [
                'date' => $dateStr,
                'profit' => $found ? (float) $found->profit : 0,
                'trades' => $found ? (int) $found->trades : 0,
            ];

            $currentDate->addDay();
        }

        return $result;
    }

    /**
     * Get today's trades.
     */
    public function getTodaysTrades(User $user): Collection
    {
        $accountIds = $user->accounts()->pluck('id');

        return Trade::whereIn('account_id', $accountIds)
            ->whereDate('close_time', today())
            ->orWhere(function ($query) use ($accountIds) {
                $query->whereIn('account_id', $accountIds)
                    ->where('status', 'open');
            })
            ->with('account:id,name,broker_name')
            ->orderByDesc('close_time')
            ->limit(20)
            ->get();
    }

    /**
     * Get trading statistics by pair.
     */
    public function getStatsByPair(User $user, int $days = 30): array
    {
        $accountIds = $user->accounts()->pluck('id');
        $startDate = now()->subDays($days)->startOfDay();

        return Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->selectRaw('pair, COUNT(*) as trades, SUM(profit) as profit, 
                         SUM(CASE WHEN profit > 0 THEN 1 ELSE 0 END) as wins,
                         AVG(profit) as avg_profit')
            ->groupBy('pair')
            ->orderByDesc('trades')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'pair' => $item->pair,
                    'trades' => (int) $item->trades,
                    'profit' => round((float) $item->profit, 2),
                    'winrate' => $item->trades > 0 ? round(($item->wins / $item->trades) * 100, 1) : 0,
                    'avg_profit' => round((float) $item->avg_profit, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get trading statistics by time of day.
     */
    public function getStatsByHour(User $user, int $days = 30): array
    {
        $accountIds = $user->accounts()->pluck('id');
        $startDate = now()->subDays($days)->startOfDay();

        return Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->selectRaw('HOUR(open_time) as hour, COUNT(*) as trades, SUM(profit) as profit')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => (int) $item->hour,
                    'trades' => (int) $item->trades,
                    'profit' => round((float) $item->profit, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get monthly P/L chart data.
     */
    public function getMonthlyPnL(User $user, int $months = 12): array
    {
        $accountIds = $user->accounts()->pluck('id');
        $startDate = now()->subMonths($months)->startOfMonth();

        $data = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->selectRaw('YEAR(close_time) as year, MONTH(close_time) as month, 
                         SUM(profit) as profit, COUNT(*) as trades')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        // Fill missing months
        $result = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= now()) {
            $year = $currentDate->year;
            $month = $currentDate->month;
            $found = $data->first(function ($item) use ($year, $month) {
                return (int) $item->year === $year && (int) $item->month === $month;
            });

            $result[] = [
                'year' => $year,
                'month' => $month,
                'label' => $currentDate->format('M Y'),
                'profit' => $found ? (float) $found->profit : 0,
                'trades' => $found ? (int) $found->trades : 0,
            ];

            $currentDate->addMonth();
        }

        return $result;
    }

    /**
     * Get calendar data for a month.
     */
    public function getCalendarData(User $user, int $year, int $month): array
    {
        $accountIds = $user->accounts()->pluck('id');
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get trades grouped by day
        $trades = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->whereBetween('close_time', [$startDate, $endDate])
            ->selectRaw('DATE(close_time) as date, COUNT(*) as trades, SUM(profit) as profit')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayData = $trades->get($dateStr);

            $result[$dateStr] = [
                'date' => $dateStr,
                'day' => $currentDate->day,
                'trades' => $dayData ? (int) $dayData->trades : 0,
                'profit' => $dayData ? round((float) $dayData->profit, 2) : 0,
            ];

            $currentDate->addDay();
        }

        return $result;
    }
}

