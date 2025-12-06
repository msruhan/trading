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
     */
    public function latestBalance(): HasOne
    {
        return $this->hasOne(Balance::class)->latestOfMany('timestamp');
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
     * Get account statistics.
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
        $winningTrades = $trades->where('profit', '>', 0)->count();
        $losingTrades = $trades->where('profit', '<', 0)->count();
        $totalProfit = $trades->sum('profit');
        $grossProfit = $trades->where('profit', '>', 0)->sum('profit');
        $grossLoss = abs($trades->where('profit', '<', 0)->sum('profit'));

        return [
            'total_trades' => $totalTrades,
            'winning_trades' => $winningTrades,
            'losing_trades' => $losingTrades,
            'winrate' => $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 2) : 0,
            'total_profit' => round($totalProfit, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_loss' => round($grossLoss, 2),
            'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : 0,
            'average_profit' => $totalTrades > 0 ? round($totalProfit / $totalTrades, 2) : 0,
        ];
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
     */
    public function getEquityCurve(int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        
        return $this->balances()
            ->where('timestamp', '>=', $startDate)
            ->orderBy('timestamp')
            ->get(['timestamp', 'balance', 'equity', 'floating_pl'])
            ->toArray();
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

