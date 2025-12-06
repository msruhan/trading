<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar',
        'timezone',
        'settings',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'settings' => 'array',
        'two_factor_confirmed_at' => 'datetime',
    ];

    /**
     * Get all trading accounts for the user.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Get all manual entries for the user.
     */
    public function manualEntries(): HasMany
    {
        return $this->hasMany(ManualEntry::class);
    }

    /**
     * Get all activity logs for the user.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Get user's theme preference.
     */
    public function getThemeAttribute(): string
    {
        return $this->settings['theme'] ?? 'dark';
    }

    /**
     * Get aggregated stats across all accounts.
     */
    public function getAggregatedStats(): array
    {
        $accounts = $this->accounts()->with(['latestBalance', 'trades'])->get();
        
        $totalBalance = 0;
        $totalEquity = 0;
        $totalProfit = 0;
        $totalTrades = 0;
        $winningTrades = 0;

        foreach ($accounts as $account) {
            if ($account->latestBalance) {
                $totalBalance += $account->latestBalance->balance;
                $totalEquity += $account->latestBalance->equity;
            }
            
            foreach ($account->trades as $trade) {
                if ($trade->status === 'closed' && $trade->profit !== null) {
                    $totalProfit += $trade->profit;
                    $totalTrades++;
                    if ($trade->profit > 0) {
                        $winningTrades++;
                    }
                }
            }
        }

        return [
            'total_balance' => $totalBalance,
            'total_equity' => $totalEquity,
            'total_profit' => $totalProfit,
            'total_trades' => $totalTrades,
            'winrate' => $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 2) : 0,
        ];
    }
}

