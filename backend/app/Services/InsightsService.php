<?php

namespace App\Services;

use App\Models\Trade;
use App\Models\Account;
use App\Models\NewsItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InsightsService
{
    /**
     * Get all trading insights for a user.
     */
    public function getInsights(User $user, int $days = 90): array
    {
        $accountIds = $user->accounts()->pluck('id')->toArray();
        
        // Return empty data if no accounts
        if (empty($accountIds)) {
            return [
                'best_day_of_week' => ['days' => [], 'best_day' => null, 'worst_day' => null],
                'best_hours' => ['hours' => [], 'best_hour' => null, 'worst_hour' => null],
                'most_stable_pairs' => ['pairs' => [], 'most_stable' => null],
                'winrate_by_hour' => ['hours' => [], 'best_hour' => null, 'worst_hour' => null],
                'reversal_patterns' => ['patterns' => [], 'most_common' => null, 'details' => []],
                'news_impact' => ['by_impact' => [], 'most_dangerous_currency' => null, 'total_danger_events' => 0],
                'profit_by_session' => ['sessions' => [], 'best_session' => null],
            ];
        }
        
        return [
            'best_day_of_week' => $this->getBestDayOfWeek($accountIds, $days),
            'best_hours' => $this->getBestHours($accountIds, $days),
            'most_stable_pairs' => $this->getMostStablePairs($accountIds, $days),
            'winrate_by_hour' => $this->getWinrateByHour($accountIds, $days),
            'reversal_patterns' => $this->getReversalPatterns($accountIds, $days),
            'news_impact' => $this->getNewsImpact($user->id, $days),
            'profit_by_session' => $this->getProfitBySession($accountIds, $days),
        ];
    }

    /**
     * Get best day of week for trading.
     */
    private function getBestDayOfWeek(array $accountIds, int $days): array
    {
        if (empty($accountIds)) {
            return ['days' => [], 'best_day' => null, 'worst_day' => null];
        }
        
        $startDate = now()->subDays($days)->startOfDay();
        
        $trades = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->selectRaw('
                DAYNAME(close_time) as day_name,
                DAYOFWEEK(close_time) as day_number,
                COUNT(*) as total_trades,
                SUM(profit) as total_profit,
                AVG(profit) as avg_profit,
                SUM(CASE WHEN profit > 0 THEN 1 ELSE 0 END) as winning_trades
            ')
            ->groupBy('day_name', 'day_number')
            ->orderBy('day_number')
            ->get();

        $bestDay = null;
        $worstDay = null;
        $maxProfit = -999999;
        $minProfit = 999999;

        $daysData = [];
        foreach ($trades as $trade) {
            $winrate = $trade->total_trades > 0 
                ? round(($trade->winning_trades / $trade->total_trades) * 100, 2)
                : 0;

            $daysData[] = [
                'day_name' => $trade->day_name,
                'day_number' => $trade->day_number,
                'total_trades' => $trade->total_trades,
                'total_profit' => round($trade->total_profit, 2),
                'avg_profit' => round($trade->avg_profit, 2),
                'winrate' => $winrate,
            ];

            if ($trade->total_profit > $maxProfit) {
                $maxProfit = $trade->total_profit;
                $bestDay = $daysData[count($daysData) - 1];
            }
            if ($trade->total_profit < $minProfit) {
                $minProfit = $trade->total_profit;
                $worstDay = $daysData[count($daysData) - 1];
            }
        }

        return [
            'days' => $daysData,
            'best_day' => $bestDay,
            'worst_day' => $worstDay,
        ];
    }

    /**
     * Get best and worst trading hours.
     */
    private function getBestHours(array $accountIds, int $days): array
    {
        if (empty($accountIds)) {
            return ['hours' => [], 'best_hour' => null, 'worst_hour' => null];
        }
        
        $startDate = now()->subDays($days)->startOfDay();
        
        $trades = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->selectRaw('
                HOUR(close_time) as hour,
                COUNT(*) as total_trades,
                SUM(profit) as total_profit,
                AVG(profit) as avg_profit,
                SUM(CASE WHEN profit > 0 THEN 1 ELSE 0 END) as winning_trades
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $bestHour = null;
        $worstHour = null;
        $maxProfit = -999999;
        $minProfit = 999999;

        $hoursData = [];
        foreach ($trades as $trade) {
            $winrate = $trade->total_trades > 0 
                ? round(($trade->winning_trades / $trade->total_trades) * 100, 2)
                : 0;

            $hoursData[] = [
                'hour' => $trade->hour,
                'total_trades' => $trade->total_trades,
                'total_profit' => round($trade->total_profit, 2),
                'avg_profit' => round($trade->avg_profit, 2),
                'winrate' => $winrate,
            ];

            if ($trade->total_profit > $maxProfit) {
                $maxProfit = $trade->total_profit;
                $bestHour = $hoursData[count($hoursData) - 1];
            }
            if ($trade->total_profit < $minProfit) {
                $minProfit = $trade->total_profit;
                $worstHour = $hoursData[count($hoursData) - 1];
            }
        }

        return [
            'hours' => $hoursData,
            'best_hour' => $bestHour,
            'worst_hour' => $worstHour,
        ];
    }

    /**
     * Get most stable trading pairs.
     */
    private function getMostStablePairs(array $accountIds, int $days): array
    {
        if (empty($accountIds)) {
            return ['pairs' => [], 'most_stable' => null];
        }
        
        $startDate = now()->subDays($days)->startOfDay();
        
        $trades = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->selectRaw('
                pair,
                COUNT(*) as total_trades,
                SUM(profit) as total_profit,
                AVG(profit) as avg_profit,
                STDDEV(profit) as stddev_profit,
                SUM(CASE WHEN profit > 0 THEN 1 ELSE 0 END) as winning_trades,
                MIN(profit) as min_profit,
                MAX(profit) as max_profit
            ')
            ->groupBy('pair')
            ->having('total_trades', '>=', 5) // Minimum 5 trades
            ->get();

        $pairsData = [];
        foreach ($trades as $trade) {
            $winrate = $trade->total_trades > 0 
                ? round(($trade->winning_trades / $trade->total_trades) * 100, 2)
                : 0;

            // Calculate stability score: lower stddev = more stable
            // Also consider winrate and consistency
            $stddev = $trade->stddev_profit ?? 0;
            $stabilityScore = $stddev > 0 
                ? round(100 - min(100, ($stddev / abs($trade->avg_profit ?: 1)) * 100), 2)
                : 100;

            $pairsData[] = [
                'pair' => $trade->pair,
                'total_trades' => $trade->total_trades,
                'total_profit' => round($trade->total_profit, 2),
                'avg_profit' => round($trade->avg_profit, 2),
                'stddev_profit' => round($stddev, 2),
                'stability_score' => $stabilityScore,
                'winrate' => $winrate,
                'min_profit' => round($trade->min_profit, 2),
                'max_profit' => round($trade->max_profit, 2),
            ];
        }

        // Sort by stability score (highest first)
        usort($pairsData, function($a, $b) {
            return $b['stability_score'] <=> $a['stability_score'];
        });

        return [
            'pairs' => array_slice($pairsData, 0, 10), // Top 10
            'most_stable' => $pairsData[0] ?? null,
        ];
    }

    /**
     * Get winrate by hour.
     */
    private function getWinrateByHour(array $accountIds, int $days): array
    {
        if (empty($accountIds)) {
            return ['hours' => [], 'best_hour' => null, 'worst_hour' => null];
        }
        
        $startDate = now()->subDays($days)->startOfDay();
        
        $trades = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->selectRaw('
                HOUR(close_time) as hour,
                COUNT(*) as total_trades,
                SUM(CASE WHEN profit > 0 THEN 1 ELSE 0 END) as winning_trades,
                SUM(profit) as total_profit
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $hoursData = [];
        foreach ($trades as $trade) {
            $winrate = $trade->total_trades > 0 
                ? round(($trade->winning_trades / $trade->total_trades) * 100, 2)
                : 0;

            $hoursData[] = [
                'hour' => $trade->hour,
                'total_trades' => $trade->total_trades,
                'winning_trades' => $trade->winning_trades,
                'losing_trades' => $trade->total_trades - $trade->winning_trades,
                'winrate' => $winrate,
                'total_profit' => round($trade->total_profit, 2),
            ];
        }

        // Find best and worst hours by winrate
        $bestHour = null;
        $worstHour = null;
        $maxWinrate = -1;
        $minWinrate = 101;

        foreach ($hoursData as $hour) {
            if ($hour['winrate'] > $maxWinrate && $hour['total_trades'] >= 3) {
                $maxWinrate = $hour['winrate'];
                $bestHour = $hour;
            }
            if ($hour['winrate'] < $minWinrate && $hour['total_trades'] >= 3) {
                $minWinrate = $hour['winrate'];
                $worstHour = $hour;
            }
        }

        return [
            'hours' => $hoursData,
            'best_hour' => $bestHour,
            'worst_hour' => $worstHour,
        ];
    }

    /**
     * Get most common reversal patterns.
     */
    private function getReversalPatterns(array $accountIds, int $days): array
    {
        if (empty($accountIds)) {
            return ['patterns' => [], 'most_common' => null, 'details' => []];
        }
        
        $startDate = now()->subDays($days)->startOfDay();
        
        // Analyze trades for reversal patterns
        // Pattern: Win -> Loss -> Win (or Loss -> Win -> Loss)
        $trades = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->orderBy('close_time')
            ->get(['id', 'account_id', 'pair', 'profit', 'close_time']);

        $patterns = [
            'win_loss_win' => 0,
            'loss_win_loss' => 0,
            'win_win_loss' => 0,
            'loss_loss_win' => 0,
        ];

        $patternDetails = [];

        // Group by pair and analyze sequences
        $tradesByPair = $trades->groupBy('pair');
        
        foreach ($tradesByPair as $pair => $pairTrades) {
            $sequence = [];
            foreach ($pairTrades->take(100) as $trade) { // Limit to last 100 trades per pair
                $sequence[] = $trade->profit > 0 ? 'W' : 'L';
                
                if (count($sequence) >= 3) {
                    $lastThree = implode('', array_slice($sequence, -3));
                    
                    if ($lastThree === 'WLW') {
                        $patterns['win_loss_win']++;
                        $patternDetails[] = [
                            'pattern' => 'Win → Loss → Win',
                            'pair' => $pair,
                            'time' => $trade->close_time,
                        ];
                    } elseif ($lastThree === 'LWL') {
                        $patterns['loss_win_loss']++;
                        $patternDetails[] = [
                            'pattern' => 'Loss → Win → Loss',
                            'pair' => $pair,
                            'time' => $trade->close_time,
                        ];
                    } elseif ($lastThree === 'WWL') {
                        $patterns['win_win_loss']++;
                    } elseif ($lastThree === 'LLW') {
                        $patterns['loss_loss_win']++;
                    }
                }
            }
        }

        // Sort patterns by frequency
        arsort($patterns);
        
        $patternList = [];
        foreach ($patterns as $pattern => $count) {
            $patternList[] = [
                'pattern' => $this->formatPatternName($pattern),
                'count' => $count,
                'frequency' => $count > 0 ? round(($count / array_sum($patterns)) * 100, 2) : 0,
            ];
        }

        return [
            'patterns' => $patternList,
            'most_common' => $patternList[0] ?? null,
            'details' => array_slice($patternDetails, 0, 20), // Last 20 occurrences
        ];
    }

    /**
     * Format pattern name for display.
     */
    private function formatPatternName(string $pattern): string
    {
        $names = [
            'win_loss_win' => 'Win → Loss → Win',
            'loss_win_loss' => 'Loss → Win → Loss',
            'win_win_loss' => 'Win → Win → Loss',
            'loss_loss_win' => 'Loss → Loss → Win',
        ];

        return $names[$pattern] ?? $pattern;
    }

    /**
     * Get news impact analysis.
     */
    private function getNewsImpact(int $userId, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        
        // Get news items marked as dangerous for EA
        $newsItems = NewsItem::where('created_by', $userId)
            ->where('date', '>=', $startDate)
            ->where('ea_status', 'danger')
            ->selectRaw('
                impact,
                currency,
                COUNT(*) as total_count,
                SUM(CASE WHEN should_disable_ea = 1 THEN 1 ELSE 0 END) as disable_ea_count
            ')
            ->groupBy('impact', 'currency')
            ->orderByDesc('total_count')
            ->get();

        $impactData = [];
        foreach ($newsItems as $news) {
            $impactData[] = [
                'impact' => $news->impact,
                'currency' => $news->currency,
                'total_count' => $news->total_count,
                'disable_ea_count' => $news->disable_ea_count,
                'percentage' => round(($news->total_count / $newsItems->sum('total_count')) * 100, 2),
            ];
        }

        // Get most dangerous currency
        $currencyImpact = NewsItem::where('created_by', $userId)
            ->where('date', '>=', $startDate)
            ->where('ea_status', 'danger')
            ->selectRaw('
                currency,
                COUNT(*) as total_count
            ')
            ->groupBy('currency')
            ->orderByDesc('total_count')
            ->get();

        $mostDangerousCurrency = $currencyImpact->first();

        return [
            'by_impact' => $impactData,
            'most_dangerous_currency' => $mostDangerousCurrency ? [
                'currency' => $mostDangerousCurrency->currency,
                'count' => $mostDangerousCurrency->total_count,
            ] : null,
            'total_danger_events' => $newsItems->sum('total_count'),
        ];
    }

    /**
     * Get profit by trading session (Tokyo/London/NY).
     */
    private function getProfitBySession(array $accountIds, int $days): array
    {
        if (empty($accountIds)) {
            return ['sessions' => [], 'best_session' => null];
        }
        
        $startDate = now()->subDays($days)->startOfDay();
        
        $trades = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->get(['profit', 'close_time']);

        $sessions = [
            'tokyo' => ['profit' => 0, 'trades' => 0, 'winning' => 0],
            'london' => ['profit' => 0, 'trades' => 0, 'winning' => 0],
            'new_york' => ['profit' => 0, 'trades' => 0, 'winning' => 0],
            'overlap' => ['profit' => 0, 'trades' => 0, 'winning' => 0],
        ];

        foreach ($trades as $trade) {
            $hour = Carbon::parse($trade->close_time)->hour;
            $session = $this->getSession($hour);
            
            $sessions[$session]['profit'] += $trade->profit;
            $sessions[$session]['trades']++;
            if ($trade->profit > 0) {
                $sessions[$session]['winning']++;
            }
        }

        $sessionData = [];
        foreach ($sessions as $sessionName => $data) {
            $winrate = $data['trades'] > 0 
                ? round(($data['winning'] / $data['trades']) * 100, 2)
                : 0;

            $sessionData[] = [
                'session' => ucfirst($sessionName),
                'session_name' => $this->getSessionDisplayName($sessionName),
                'total_profit' => round($data['profit'], 2),
                'total_trades' => $data['trades'],
                'winning_trades' => $data['winning'],
                'losing_trades' => $data['trades'] - $data['winning'],
                'winrate' => $winrate,
                'avg_profit' => $data['trades'] > 0 ? round($data['profit'] / $data['trades'], 2) : 0,
            ];
        }

        // Find best session
        $bestSession = null;
        $maxProfit = -999999;
        foreach ($sessionData as $session) {
            if ($session['total_profit'] > $maxProfit) {
                $maxProfit = $session['total_profit'];
                $bestSession = $session;
            }
        }

        return [
            'sessions' => $sessionData,
            'best_session' => $bestSession,
        ];
    }

    /**
     * Determine trading session based on hour (UTC).
     */
    private function getSession(int $hour): string
    {
        // Tokyo: 00:00 - 09:00 UTC
        // London: 08:00 - 16:00 UTC
        // New York: 13:00 - 21:00 UTC
        // Overlap: London-NY (13:00-16:00), Tokyo-London (08:00-09:00)
        
        if (($hour >= 13 && $hour < 16) || ($hour >= 8 && $hour < 9)) {
            return 'overlap';
        } elseif ($hour >= 0 && $hour < 9) {
            return 'tokyo';
        } elseif ($hour >= 8 && $hour < 16) {
            return 'london';
        } elseif ($hour >= 13 && $hour < 21) {
            return 'new_york';
        } else {
            return 'tokyo'; // Default
        }
    }

    /**
     * Get display name for session.
     */
    private function getSessionDisplayName(string $session): string
    {
        $names = [
            'tokyo' => 'Tokyo Session (00:00-09:00 UTC)',
            'london' => 'London Session (08:00-16:00 UTC)',
            'new_york' => 'New York Session (13:00-21:00 UTC)',
            'overlap' => 'Overlap Session (13:00-16:00 UTC)',
        ];

        return $names[$session] ?? $session;
    }
}

