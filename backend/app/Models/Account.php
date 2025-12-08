<?php

namespace App\Models;

use App\Services\EncryptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'broker_name',
        'server',
        'login',
        'login_masked',
        'credentials_encrypted',
        'account_type',
        'platform',
        'currency',
        'leverage',
        'initial_balance',
        'status',
        'last_sync_at',
        'sync_requested_at',
        'sync_interval',
        'is_auto_sync',
        'api_token',
        'error_message',
        'meta',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'is_auto_sync' => 'boolean',
        'meta' => 'array',
        'initial_balance' => 'decimal:2',
    ];

    protected $hidden = [
        'credentials_encrypted',
        'api_token',
    ];

    /**
     * Get the user that owns the account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all trades for the account.
     */
    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    /**
     * Get all balance snapshots for the account.
     */
    public function balances(): HasMany
    {
        return $this->hasMany(Balance::class);
    }

    /**
     * Get the latest balance snapshot.
     * Always prioritize real-time sync data (from EA) over historical snapshots.
     * Real-time sync balance is identified by having the latest timestamp (from now() in EABridgeController).
     * Historical snapshots are created with end-of-day timestamps (23:59:59 of previous day).
     * 
     * Strategy: Get balance with latest timestamp, which should always be real-time sync data.
     * If multiple balances have the same latest timestamp, prioritize real-time indicators.
     */
    public function latestBalance(): HasOne
    {
        // Real-time sync balance always has the latest timestamp (from now() in EABridgeController)
        // Historical snapshots have end-of-day timestamps (23:59:59), which are always older
        // So latestOfMany('timestamp') should always return real-time sync balance
        // But we add a secondary sort to handle edge cases where timestamps might be identical
        return $this->hasOne(Balance::class)
            ->orderByRaw('CASE 
                WHEN (margin > 0 OR open_trades_count > 0 OR (margin_level IS NOT NULL AND margin_level > 0)) THEN 0 
                WHEN (margin = 0 AND open_trades_count = 0 AND (margin_level IS NULL OR margin_level = 0)) THEN 1 
                ELSE 0 
            END')
            ->latestOfMany('timestamp');
    }

    /**
     * Get all sync logs for the account.
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    /**
     * Get the latest sync log.
     */
    public function latestSyncLog(): HasOne
    {
        return $this->hasOne(SyncLog::class)->latestOfMany();
    }

    /**
     * Get manual entries linked to this account.
     */
    public function manualEntries(): HasMany
    {
        return $this->hasMany(ManualEntry::class);
    }

    /**
     * Get all EA commands for this account.
     */
    public function eaCommands(): HasMany
    {
        return $this->hasMany(EaCommand::class);
    }

    /**
     * Mask the login number for display.
     */
    public static function maskLogin(string $login): string
    {
        $length = strlen($login);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        return str_repeat('*', $length - 4) . substr($login, -4);
    }

    /**
     * Set the investor password (encrypted).
     */
    public function setInvestorPassword(string $password): void
    {
        $this->credentials_encrypted = app(EncryptionService::class)->encrypt($password);
    }

    /**
     * Get the decrypted investor password.
     */
    public function getInvestorPassword(): ?string
    {
        if (!$this->credentials_encrypted) {
            return null;
        }
        return app(EncryptionService::class)->decrypt($this->credentials_encrypted);
    }

    /**
     * Generate a new API token for EA Bridge.
     */
    public function generateApiToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->api_token = hash('sha256', $token);
        $this->save();
        return $token;
    }

    /**
     * Verify an API token.
     */
    public function verifyApiToken(string $token): bool
    {
        return hash_equals($this->api_token, hash('sha256', $token));
    }

    /**
     * Get account statistics (extended).
     */
    public function getStats(string $period = 'all'): array
    {
        $query = $this->trades()->where('status', 'closed');

        if ($period === 'today') {
            $query->whereDate('close_time', today());
        } elseif ($period === 'month') {
            $query->whereMonth('close_time', now()->month)
                  ->whereYear('close_time', now()->year);
        }

        $trades = $query->get();
        $totalTrades = $trades->count();
        
        // Basic stats
        $winningTrades = $trades->where('profit', '>', 0);
        $losingTrades = $trades->where('profit', '<', 0);
        $winningCount = $winningTrades->count();
        $losingCount = $losingTrades->count();
        $totalProfit = $trades->sum('profit');
        $grossProfit = $winningTrades->sum('profit');
        $grossLoss = abs($losingTrades->sum('profit'));
        
        // Volume and lots
        $totalLots = $trades->sum('lots');
        $totalVolume = $trades->count(); // Number of trades as volume
        
        // Average win/loss in dollars
        $avgWinDollar = $winningCount > 0 ? $winningTrades->sum('profit') / $winningCount : 0;
        $avgLossDollar = $losingCount > 0 ? abs($losingTrades->sum('profit')) / $losingCount : 0;
        
        // Average win/loss in pips
        $avgWinPips = $winningCount > 0 ? $winningTrades->avg('pips') : 0;
        $avgLossPips = $losingCount > 0 ? abs($losingTrades->avg('pips')) : 0;
        
        // Best and worst trades
        $bestTrade = $trades->max('profit') ?? 0;
        $worstTrade = $trades->min('profit') ?? 0;
        
        // Longs and shorts won
        $longTrades = $trades->whereIn('type', ['buy', 'buy_limit', 'buy_stop']);
        $shortTrades = $trades->whereIn('type', ['sell', 'sell_limit', 'sell_stop']);
        $longsWon = $longTrades->where('profit', '>', 0)->count();
        $shortsWon = $shortTrades->where('profit', '>', 0)->count();
        $longsTotal = $longTrades->count();
        $shortsTotal = $shortTrades->count();
        $longsWonPercent = $longsTotal > 0 ? ($longsWon / $longsTotal) * 100 : 0;
        $shortsWonPercent = $shortsTotal > 0 ? ($shortsWon / $shortsTotal) * 100 : 0;
        
        // Average trade length in minutes
        $avgTradeLength = $trades->avg('duration_minutes') ?? 0;
        
        // Risk/Reward ratio
        $riskReward = $avgLossDollar > 0 ? $avgWinDollar / $avgLossDollar : 0;
        
        // Win/Loss streaks
        $maxWinStreak = $this->calculateMaxStreak($trades, true);
        $maxLossStreak = $this->calculateMaxStreak($trades, false);

        return [
            // Basic stats
            'total_trades' => $totalTrades,
            'winning_trades' => $winningCount,
            'losing_trades' => $losingCount,
            'winrate' => $totalTrades > 0 ? round(($winningCount / $totalTrades) * 100, 2) : 0,
            'total_profit' => round($totalProfit, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_loss' => round($grossLoss, 2),
            'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : 0,
            'average_profit' => $totalTrades > 0 ? round($totalProfit / $totalTrades, 2) : 0,
            
            // Extended stats
            'total_volume' => $totalVolume,
            'total_lots' => round($totalLots, 2),
            'avg_win_dollar' => round($avgWinDollar, 2),
            'avg_loss_dollar' => round($avgLossDollar, 2),
            'avg_win_pips' => round($avgWinPips, 1),
            'avg_loss_pips' => round($avgLossPips, 1),
            'best_trade' => round($bestTrade, 2),
            'worst_trade' => round($worstTrade, 2),
            'longs_won_percent' => round($longsWonPercent, 1),
            'shorts_won_percent' => round($shortsWonPercent, 1),
            'longs_total' => $longsTotal,
            'shorts_total' => $shortsTotal,
            'avg_trade_length' => round($avgTradeLength, 0),
            'risk_reward' => round($riskReward, 2),
            'max_win_streak' => $maxWinStreak,
            'max_loss_streak' => $maxLossStreak,
        ];
    }
    
    /**
     * Calculate maximum win or loss streak.
     */
    protected function calculateMaxStreak($trades, bool $isWin): int
    {
        $sortedTrades = $trades->sortBy('close_time');
        $maxStreak = 0;
        $currentStreak = 0;
        
        foreach ($sortedTrades as $trade) {
            $isProfit = $trade->profit > 0;
            
            if (($isWin && $isProfit) || (!$isWin && !$isProfit)) {
                $currentStreak++;
                $maxStreak = max($maxStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }
        
        return $maxStreak;
    }
    
    /**
     * Get period statistics (Today, This Week, This Month, This Year).
     */
    public function getPeriodStats(): array
    {
        $periods = [
            'today' => [
                'start' => now()->startOfDay(),
                'end' => now()->endOfDay(),
            ],
            'this_week' => [
                'start' => now()->startOfWeek(),
                'end' => now()->endOfWeek(),
            ],
            'this_month' => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
            ],
            'this_year' => [
                'start' => now()->startOfYear(),
                'end' => now()->endOfYear(),
            ],
        ];
        
        $result = [];
        $initialBalance = (float) ($this->initial_balance ?? 0);
        
        foreach ($periods as $periodKey => $dateRange) {
            $trades = $this->trades()
                ->where('status', 'closed')
                ->whereBetween('close_time', [$dateRange['start'], $dateRange['end']])
                ->get();
            
            $totalTrades = $trades->count();
            $totalProfit = $trades->sum('profit');
            $totalPips = $trades->sum('pips');
            $totalLots = $trades->sum('lots');
            $winningTrades = $trades->where('profit', '>', 0)->count();
            $winrate = $totalTrades > 0 ? ($winningTrades / $totalTrades) * 100 : 0;
            
            // Calculate gain percentage
            $gain = $initialBalance > 0 ? ($totalProfit / $initialBalance) * 100 : 0;
            
            $result[$periodKey] = [
                'gain' => round($gain, 2),
                'profit' => round($totalProfit, 2),
                'pips' => round($totalPips, 1),
                'winrate' => round($winrate, 0),
                'trades' => $totalTrades,
                'lots' => round($totalLots, 2),
            ];
        }
        
        return $result;
    }
    
    /**
     * Get account info panel data.
     */
    public function getAccountInfo(): array
    {
        $latestBalance = $this->latestBalance;
        $currentBalance = (float) ($latestBalance->balance ?? 0);
        $currentEquity = (float) ($latestBalance->equity ?? $currentBalance);
        
        // Get initial balance - use account initial_balance, or earliest balance, or earliest trade balance
        $initialBalance = (float) ($this->initial_balance ?? 0);
        
        // If initial_balance is 0, try to get from earliest balance snapshot
        if ($initialBalance <= 0) {
            $earliestBalance = $this->balances()->orderBy('timestamp', 'asc')->first();
            if ($earliestBalance) {
                $initialBalance = (float) $earliestBalance->balance;
            }
        }
        
        // If still 0, calculate from earliest trade
        if ($initialBalance <= 0) {
            $earliestTrade = $this->trades()
                ->where('status', 'closed')
                ->orderBy('close_time', 'asc')
                ->first();
            
            if ($earliestTrade) {
                // Calculate initial balance by subtracting all profits from current balance
                $totalProfitFromTrades = $this->trades()
                    ->where('status', 'closed')
                    ->sum('profit');
                $initialBalance = $currentBalance - (float) $totalProfitFromTrades;
                
                // Ensure initial balance is positive
                if ($initialBalance <= 0) {
                    $initialBalance = $currentBalance; // Fallback to current balance
                }
            } else {
                // No trades, use current balance as initial
                $initialBalance = $currentBalance > 0 ? $currentBalance : 0;
            }
        }
        
        // Calculate gains
        $totalProfit = $currentBalance - $initialBalance;
        $gain = $initialBalance > 0 ? (($currentBalance - $initialBalance) / $initialBalance) * 100 : 0;
        
        // Deposits and withdrawals (from meta or calculate from balance changes)
        $deposits = (float) ($this->meta['deposits'] ?? 0);
        $withdrawals = (float) ($this->meta['withdrawals'] ?? 0);
        
        // If deposits not set in meta, use initial_balance as deposits
        if ($deposits <= 0) {
            $deposits = $initialBalance;
        }
        
        // Absolute gain (based on deposits/withdrawals)
        $netDeposits = $deposits - $withdrawals;
        $absGain = $netDeposits > 0 ? (($currentBalance - $netDeposits) / $netDeposits) * 100 : 0;
        
        // Daily gain - calculate from balance at start of day vs current
        $startOfDayBalance = $this->balances()
            ->whereDate('timestamp', today())
            ->orderBy('timestamp', 'asc')
            ->first();
        
        $balanceAtStartOfDay = $startOfDayBalance 
            ? (float) $startOfDayBalance->balance 
            : $this->balances()
                ->where('timestamp', '<', today()->startOfDay())
                ->orderBy('timestamp', 'desc')
                ->first()
                ?->balance ?? $currentBalance;
        
        $dailyProfit = $currentBalance - $balanceAtStartOfDay;
        $dailyGain = $balanceAtStartOfDay > 0 ? ($dailyProfit / $balanceAtStartOfDay) * 100 : 0;
        
        // Monthly gain - calculate from balance at start of month vs current
        $startOfMonthBalance = $this->balances()
            ->where('timestamp', '>=', now()->startOfMonth())
            ->orderBy('timestamp', 'asc')
            ->first();
        
        $balanceAtStartOfMonth = $startOfMonthBalance 
            ? (float) $startOfMonthBalance->balance 
            : $this->balances()
                ->where('timestamp', '<', now()->startOfMonth())
                ->orderBy('timestamp', 'desc')
                ->first()
                ?->balance ?? $initialBalance;
        
        $monthlyProfit = $currentBalance - $balanceAtStartOfMonth;
        $monthlyGain = $balanceAtStartOfMonth > 0 ? ($monthlyProfit / $balanceAtStartOfMonth) * 100 : 0;
        
        // Drawdown calculation
        $highestBalance = $this->balances()->max('balance') ?? $currentBalance;
        $drawdown = $highestBalance > 0 && $highestBalance > $currentBalance 
            ? (($highestBalance - $currentBalance) / $highestBalance) * 100 
            : 0;
        
        // Find highest balance date
        $highestBalanceRecord = $this->balances()
            ->orderBy('balance', 'desc')
            ->first();
        $highestDate = $highestBalanceRecord ? $highestBalanceRecord->timestamp->format('M d') : null;
        
        // Equity percentage
        $equityPercent = $currentBalance > 0 ? ($currentEquity / $currentBalance) * 100 : 100;
        
        return [
            'gain' => round($gain, 2),
            'abs_gain' => round($absGain, 2),
            'daily_gain' => round($dailyGain, 2),
            'monthly_gain' => round($monthlyGain, 2),
            'drawdown' => round($drawdown, 2),
            'balance' => round($currentBalance, 2),
            'equity' => round($currentEquity, 2),
            'equity_percent' => round($equityPercent, 2),
            'highest_balance' => round($highestBalance, 2),
            'highest_date' => $highestDate,
            'total_profit' => round($totalProfit, 2),
            'interest' => 0,
            'deposits' => round($deposits, 2),
            'withdrawals' => round($withdrawals, 2),
            'last_updated' => $this->last_sync_at ? $this->last_sync_at->diffForHumans() : '-',
            'tracking' => 0,
        ];
    }

    /**
     * Get equity curve for a specific month.
     */
    public function getEquityCurveForMonth(int $year, int $month): array
    {
        $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        // Get all dates in the month
        $dates = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->addDay();
        }
        
        // Get balance snapshots for the month
        $balanceSnapshots = $this->balances()
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->orderBy('timestamp', 'asc')
            ->get()
            ->groupBy(function ($balance) {
                return $balance->timestamp->format('Y-m-d');
            })
            ->map(function ($balances) {
                return $balances->last(); // Get latest balance for each day
            });
        
        // Get all trade dates in the month
        $tradeDates = $this->trades()
            ->where('status', 'closed')
            ->whereBetween('close_time', [$startDate, $endDate])
            ->whereNotNull('close_time')
            ->selectRaw('DATE(close_time) as date')
            ->distinct()
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($item) {
                return $item->date;
            })
            ->unique()
            ->values();
        
        // Generate balance snapshots for trade dates that don't have one
        foreach ($tradeDates as $date) {
            if (!$balanceSnapshots->has($date)) {
                $balanceForDate = $this->generateBalanceSnapshotForDate($date);
                if ($balanceForDate) {
                    $balanceSnapshots->put($date, (object) $balanceForDate);
                }
            }
        }
        
        // Build result array with all dates in the month
        $result = [];
        foreach ($dates as $date) {
            if ($balanceSnapshots->has($date)) {
                $snapshot = $balanceSnapshots->get($date);
                $result[] = [
                    'date' => $date,
                    'balance' => (float) ($snapshot->balance ?? $snapshot['balance'] ?? 0),
                    'equity' => (float) ($snapshot->equity ?? $snapshot['equity'] ?? 0),
                ];
            } else {
                // If no balance snapshot, use previous day's balance or initial balance
                $prevBalance = !empty($result) ? $result[count($result) - 1]['balance'] : $this->initial_balance ?? 0;
                $result[] = [
                    'date' => $date,
                    'balance' => (float) $prevBalance,
                    'equity' => (float) $prevBalance,
                ];
            }
        }
        
        return $result;
    }

    /**
     * Get daily P/L for a date range.
     */
    public function getDailyPnL(int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        
        return $this->trades()
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->selectRaw('DATE(close_time) as date, SUM(profit) as profit, COUNT(*) as trades')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();
    }

    /**
     * Get equity curve data.
     * If balance snapshots are missing, generate them from trades.
     * For each day, use the latest balance snapshot (by timestamp) to ensure accuracy.
     * Start from the first trade date, not a fixed number of days back.
     */
    public function getEquityCurve(int $days = 30): array
    {
        // Get the first trade date to determine the actual start date
        $firstTrade = $this->trades()
            ->where('status', 'closed')
            ->whereNotNull('close_time')
            ->orderBy('close_time', 'asc')
            ->first();
        
        // Use first trade date or days back, whichever is more recent
        if ($firstTrade && $firstTrade->close_time) {
            $firstTradeDate = $firstTrade->close_time->startOfDay();
            $daysBackDate = now()->subDays($days)->startOfDay();
            // Start from the earlier date (first trade or days back)
            $startDate = $firstTradeDate->lt($daysBackDate) ? $firstTradeDate : $daysBackDate;
        } else {
        $startDate = now()->subDays($days)->startOfDay();
        }
        
        // Get existing balance snapshots, grouped by date and take the latest for each day
        $balanceSnapshots = $this->balances()
            ->where('timestamp', '>=', $startDate)
            ->orderBy('timestamp', 'desc') // Get latest first
            ->get(['timestamp', 'balance', 'equity', 'floating_pl'])
            ->groupBy(function ($balance) {
                return $balance->timestamp->format('Y-m-d');
            })
            ->map(function ($balances) {
                // For each day, take the latest balance snapshot (first one since we ordered desc)
                return $balances->first();
            });
        
        // If we have trades but missing balance snapshots, generate them
        $hasTrades = $this->trades()
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->exists();
        
        if ($hasTrades && $balanceSnapshots->count() < 3) {
            // Generate balance snapshots from trades
            $this->generateBalanceSnapshotsFromTrades($startDate);
            
            // Re-fetch balance snapshots (use latest for each day)
            $balanceSnapshots = $this->balances()
                ->where('timestamp', '>=', $startDate)
                ->orderBy('timestamp', 'desc') // Get latest first
                ->get(['timestamp', 'balance', 'equity', 'floating_pl'])
                ->groupBy(function ($balance) {
                    return $balance->timestamp->format('Y-m-d');
                })
                ->map(function ($balances) {
                    // For each day, take the latest balance snapshot (first one since we ordered desc)
                    return $balances->first();
                });
        }
        
        // Get all dates that have trades (ONLY dates with closed trades)
        $tradeDates = $this->trades()
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->whereNotNull('close_time')
            ->selectRaw('DATE(close_time) as date')
            ->distinct()
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($item) {
                $date = $item->date;
                if ($date instanceof \Carbon\Carbon) {
                    return $date->format('Y-m-d');
                }
                return is_string($date) ? $date : date('Y-m-d', strtotime($date));
            })
            ->unique()
            ->values();
        
        // Generate balance snapshots for any missing trade dates
        if ($tradeDates->isNotEmpty()) {
            // Delete existing generated balance snapshots for trade dates to recalculate correctly
            // Only delete snapshots that are clearly generated (margin = 0, open_trades_count = 0)
            // This ensures we recalculate balance correctly from initial_balance + trades
            foreach ($tradeDates as $date) {
                $dateKey = is_string($date) ? $date : $date->format('Y-m-d');
                $this->balances()
                    ->whereDate('timestamp', $dateKey)
                    ->where('margin', 0)
                    ->where('open_trades_count', 0)
                    ->delete();
            }
            
            $this->generateBalanceSnapshotsFromTrades($startDate);
            
            // Re-fetch balance snapshots after generation
            $balanceSnapshots = $this->balances()
                ->where('timestamp', '>=', $startDate)
                ->orderBy('timestamp', 'desc')
                ->get(['timestamp', 'balance', 'equity', 'floating_pl'])
                ->groupBy(function ($balance) {
                    return $balance->timestamp->format('Y-m-d');
                })
                ->map(function ($balances) {
                    return $balances->first();
                });
        } else {
            $balanceSnapshots = collect();
        }
        
        // Build result array - ONLY include dates that have trades
        // Do NOT include dates without trades (like Dec 6 if no trades on that date)
        $result = [];
        
        // Only add balance snapshots for dates that have trades
        // If balance snapshot doesn't exist for a trade date, generate it from trades
        foreach ($tradeDates as $date) {
            $dateKey = is_string($date) ? $date : $date->format('Y-m-d');
            
            if ($balanceSnapshots->has($dateKey)) {
                // Use existing balance snapshot
                $snapshot = $balanceSnapshots->get($dateKey);
                $result[] = [
                    'timestamp' => $snapshot->timestamp ?? $snapshot['timestamp'] ?? \Carbon\Carbon::parse($dateKey)->endOfDay(),
                    'balance' => $snapshot->balance ?? $snapshot['balance'] ?? 0,
                    'equity' => $snapshot->equity ?? $snapshot['equity'] ?? 0,
                    'floating_pl' => $snapshot->floating_pl ?? $snapshot['floating_pl'] ?? 0,
                ];
            } else {
                // Balance snapshot doesn't exist for this date, generate it from trades
                $balanceForDate = $this->generateBalanceSnapshotForDate($dateKey);
                
                if ($balanceForDate) {
                    $result[] = $balanceForDate;
                }
            }
        }
        
        // Sort by timestamp (ascending - oldest first, so 3 Dec -> 4 Dec -> 5 Dec)
        usort($result, function ($a, $b) {
            $timestampA = $a['timestamp'] instanceof \Carbon\Carbon 
                ? $a['timestamp'] 
                : \Carbon\Carbon::parse($a['timestamp']);
            $timestampB = $b['timestamp'] instanceof \Carbon\Carbon 
                ? $b['timestamp'] 
                : \Carbon\Carbon::parse($b['timestamp']);
            return $timestampA->lt($timestampB) ? -1 : 1;
        });
        
        return $result;
    }
    
    /**
     * Generate balance snapshot for a specific date from trades.
     */
    protected function generateBalanceSnapshotForDate(string $date): ?array
    {
        // Get initial balance - ALWAYS use initial_balance from account, not from balance records
        // Balance records may already include profit from trades, which would cause double counting
        $initialBalance = (float) ($this->initial_balance ?? 0);
        
        // Get all closed trades up to and including this date (ordered by close_time)
        // Calculate balance = initial_balance + sum of all profits from trades closed on or before this date
        $dateEnd = \Carbon\Carbon::parse($date)->endOfDay();
        
        $totalProfit = $this->trades()
            ->where('status', 'closed')
            ->where('close_time', '<=', $dateEnd)
            ->whereNotNull('close_time')
            ->sum('profit');
        
        $runningBalance = $initialBalance + (float) $totalProfit;
        
        // Get all closed trades for this date to verify there are trades
        $tradesForDate = $this->trades()
            ->where('status', 'closed')
            ->whereDate('close_time', $date)
            ->whereNotNull('close_time')
            ->get();
        
        if ($tradesForDate->isEmpty()) {
            return null;
        }
        
        // Create balance snapshot for this date (end of day)
        $balanceDate = \Carbon\Carbon::parse($date)->endOfDay();
        
        // Only create if date is in the past (not today) to avoid overwriting real-time sync data
        if ($balanceDate->isPast() && !$balanceDate->isToday()) {
            \App\Models\Balance::create([
                'account_id' => $this->id,
                'timestamp' => $balanceDate,
                'balance' => round($runningBalance, 2),
                'equity' => round($runningBalance, 2), // Equity = balance when no open trades
                'margin' => 0.00,
                'free_margin' => round($runningBalance, 2),
                'margin_level' => 0.00,
                'floating_pl' => 0.00,
                'open_trades_count' => 0,
            ]);
        }
        
        return [
            'timestamp' => $balanceDate,
            'balance' => round($runningBalance, 2),
            'equity' => round($runningBalance, 2),
            'floating_pl' => 0.00,
        ];
    }
    
    /**
     * Generate balance snapshots from closed trades.
     */
    public function generateBalanceSnapshotsFromTrades(?\Carbon\Carbon $startDate = null): void
    {
        if (!$startDate) {
            $startDate = now()->subDays(30)->startOfDay();
        }
        
        // Get initial balance - ALWAYS use initial_balance from account, not from balance records
        // Balance records may already include profit from trades, which would cause double counting
        $initialBalance = (float) ($this->initial_balance ?? 0);
        
        // Get all closed trades grouped by close date (ordered by close_time)
        $tradesByDate = $this->trades()
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->whereNotNull('close_time')
            ->orderBy('close_time')
            ->get()
            ->groupBy(function ($trade) {
                return $trade->close_time->format('Y-m-d');
            });
        
        // Calculate running balance for each date
        // For each date, balance = initial_balance + sum of all profits from trades closed on or before this date
        foreach ($tradesByDate as $date => $trades) {
            // Calculate total profit up to and including this date
            $dateEnd = \Carbon\Carbon::parse($date)->endOfDay();
            $totalProfit = $this->trades()
                ->where('status', 'closed')
                ->where('close_time', '<=', $dateEnd)
                ->whereNotNull('close_time')
                ->sum('profit');
            
            $runningBalance = $initialBalance + (float) $totalProfit;
            
            // Check if balance snapshot already exists for this date
            $existingBalance = $this->balances()
                ->whereDate('timestamp', $date)
                ->first();
            
            if (!$existingBalance) {
                // Create balance snapshot for this date
                // Only create if date is in the past (not today) to avoid overwriting real-time sync data
                $balanceDate = \Carbon\Carbon::parse($date);
                if ($balanceDate->isPast() && !$balanceDate->isToday()) {
                    \App\Models\Balance::create([
                        'account_id' => $this->id,
                        'timestamp' => $balanceDate->endOfDay(),
                        'balance' => $runningBalance,
                        'equity' => $runningBalance, // For closed trades, equity = balance
                        'margin' => 0,
                        'free_margin' => $runningBalance,
                        'margin_level' => 0.00, // Use 0.00 instead of null
                        'floating_pl' => 0,
                        'open_trades_count' => 0,
                    ]);
                }
            } else {
                // Don't update existing balance if it's from a real sync (has margin or open_trades_count > 0)
                // Only update if it's clearly a generated snapshot (margin = 0 and open_trades_count = 0)
                if ($existingBalance->margin == 0 && $existingBalance->open_trades_count == 0) {
                    // This is a generated snapshot, safe to update
                    $existingBalance->update([
                        'balance' => $runningBalance,
                        'equity' => $runningBalance,
                    ]);
                }
                // Otherwise, preserve the real sync data
            }
        }
    }

    /**
     * Scope for active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for accounts needing sync.
     */
    public function scopeNeedsSync($query)
    {
        return $query->where('is_auto_sync', true)
            ->where('status', '!=', 'syncing')
            ->where(function ($q) {
                $q->whereNull('last_sync_at')
                  ->orWhereRaw('last_sync_at < DATE_SUB(NOW(), INTERVAL sync_interval MINUTE)');
            });
    }
}

