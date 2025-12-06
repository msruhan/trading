<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Balance extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'timestamp',
        'balance',
        'equity',
        'margin',
        'free_margin',
        'margin_level',
        'floating_pl',
        'open_trades_count',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'balance' => 'decimal:2',
        'equity' => 'decimal:2',
        'margin' => 'decimal:2',
        'free_margin' => 'decimal:2',
        'margin_level' => 'decimal:2',
        'floating_pl' => 'decimal:2',
    ];

    /**
     * Get the account that owns this balance snapshot.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Calculate drawdown from initial balance.
     */
    public function getDrawdownAttribute(): float
    {
        $initial = $this->account->initial_balance;
        if ($initial <= 0) {
            return 0;
        }

        $currentEquity = $this->equity;
        if ($currentEquity >= $initial) {
            return 0;
        }

        return round((($initial - $currentEquity) / $initial) * 100, 2);
    }

    /**
     * Calculate profit percentage from initial balance.
     */
    public function getProfitPercentageAttribute(): float
    {
        $initial = $this->account->initial_balance;
        if ($initial <= 0) {
            return 0;
        }

        return round((($this->balance - $initial) / $initial) * 100, 2);
    }

    /**
     * Check if margin level is healthy (above 100%).
     */
    public function isMarginHealthy(): bool
    {
        return $this->margin_level === null || $this->margin_level > 100;
    }

    /**
     * Get margin warning status.
     */
    public function getMarginStatusAttribute(): string
    {
        if ($this->margin_level === null || $this->margin_level === 0) {
            return 'none';
        }

        if ($this->margin_level > 500) {
            return 'healthy';
        }

        if ($this->margin_level > 200) {
            return 'moderate';
        }

        if ($this->margin_level > 100) {
            return 'warning';
        }

        return 'danger';
    }
}

