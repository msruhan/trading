<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'ticket',
        'pair',
        'type',
        'status',
        'open_time',
        'close_time',
        'open_price',
        'close_price',
        'stop_loss',
        'take_profit',
        'lots',
        'profit',
        'swap',
        'commission',
        'pips',
        'duration_minutes',
        'comment',
        'magic_number',
        'is_manual',
        'tags',
        'notes',
    ];

    protected $casts = [
        'open_time' => 'datetime',
        'close_time' => 'datetime',
        'open_price' => 'decimal:8',
        'close_price' => 'decimal:8',
        'stop_loss' => 'decimal:8',
        'take_profit' => 'decimal:8',
        'lots' => 'decimal:4',
        'profit' => 'decimal:2',
        'swap' => 'decimal:2',
        'commission' => 'decimal:2',
        'pips' => 'decimal:2',
        'is_manual' => 'boolean',
        'tags' => 'array',
    ];

    /**
     * Get the account that owns the trade.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get manual entries linked to this trade.
     */
    public function manualEntries(): HasMany
    {
        return $this->hasMany(ManualEntry::class);
    }

    /**
     * Check if trade is a buy order.
     */
    public function isBuy(): bool
    {
        return in_array($this->type, ['buy', 'buy_limit', 'buy_stop']);
    }

    /**
     * Check if trade is a sell order.
     */
    public function isSell(): bool
    {
        return in_array($this->type, ['sell', 'sell_limit', 'sell_stop']);
    }

    /**
     * Check if trade is still open.
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if trade is profitable.
     */
    public function isProfitable(): bool
    {
        return $this->profit > 0;
    }

    /**
     * Get net profit (including swap and commission).
     */
    public function getNetProfitAttribute(): float
    {
        return $this->profit + $this->swap - abs($this->commission);
    }

    /**
     * Calculate pips based on pair and price difference.
     */
    public function calculatePips(): ?float
    {
        if (!$this->close_price || !$this->open_price) {
            return null;
        }

        $pipMultiplier = $this->getPipMultiplier();
        $diff = $this->close_price - $this->open_price;
        
        if ($this->isSell()) {
            $diff = -$diff;
        }

        return round($diff * $pipMultiplier, 2);
    }

    /**
     * Get pip multiplier based on pair.
     */
    protected function getPipMultiplier(): int
    {
        $pair = strtoupper($this->pair);
        
        // JPY pairs have different pip calculation
        if (str_contains($pair, 'JPY')) {
            return 100;
        }
        
        // Gold
        if (str_contains($pair, 'XAU')) {
            return 10;
        }
        
        // Most forex pairs
        return 10000;
    }

    /**
     * Calculate duration in minutes.
     */
    public function calculateDuration(): ?int
    {
        if (!$this->close_time || !$this->open_time) {
            return null;
        }

        return $this->open_time->diffInMinutes($this->close_time);
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration_minutes) {
            return '-';
        }

        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 24) {
            $days = floor($hours / 24);
            $hours = $hours % 24;
            return "{$days}d {$hours}h";
        }

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    /**
     * Scope for open trades.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope for closed trades.
     */
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    /**
     * Scope for winning trades.
     */
    public function scopeWinning($query)
    {
        return $query->where('profit', '>', 0);
    }

    /**
     * Scope for losing trades.
     */
    public function scopeLosing($query)
    {
        return $query->where('profit', '<', 0);
    }

    /**
     * Scope for a specific pair.
     */
    public function scopeForPair($query, string $pair)
    {
        return $query->where('pair', $pair);
    }

    /**
     * Scope for date range.
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        $query->where('close_time', '>=', $startDate);
        
        if ($endDate) {
            $query->where('close_time', '<=', $endDate);
        }

        return $query;
    }
}

