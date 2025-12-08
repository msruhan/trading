<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use App\Models\User;

class NewsItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'time',
        'title',
        'summary',
        'content',
        'source',
        'url',
        'impact',
        'currency',
        'actual',
        'forecast',
        'previous',
        'is_manual',
        'created_by', // Link to user (admin/demo user)
        // EA Tracking fields
        'ea_status',
        'user_notes',
        'should_disable_ea',
        'disable_minutes_before',
        'disable_minutes_after',
        'affected_pairs',
        'marked_by',
        'marked_at',
        'ff_event_id',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i:s',
        'is_manual' => 'boolean',
        'should_disable_ea' => 'boolean',
        'disable_minutes_before' => 'integer',
        'disable_minutes_after' => 'integer',
        'affected_pairs' => 'array',
        'marked_at' => 'datetime',
    ];

    protected $appends = [
        'impact_color',
        'impact_icon',
        'ea_status_color',
        'formatted_time',
        'formatted_date',
        'disable_window',
    ];

    /**
     * Get the user who created this news item.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who marked this news item.
     */
    public function markedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /**
     * Get impact color class.
     */
    public function getImpactColorAttribute(): string
    {
        return match($this->impact) {
            'high' => 'text-red-500 bg-red-500/10',
            'medium' => 'text-yellow-500 bg-yellow-500/10',
            'low' => 'text-green-500 bg-green-500/10',
            default => 'text-gray-500 bg-gray-500/10',
        };
    }

    /**
     * Get impact icon.
     */
    public function getImpactIconAttribute(): string
    {
        return match($this->impact) {
            'high' => '🔴',
            'medium' => '🟠',
            'low' => '🟡',
            default => '⚪',
        };
    }

    /**
     * Get EA status color class.
     */
    public function getEaStatusColorAttribute(): string
    {
        if (!$this->ea_status || $this->ea_status === '') {
            return 'text-dark-400 bg-dark-800/50 border-dark-700 hover:border-dark-600';
        }
        
        return match($this->ea_status) {
            'safe' => 'text-green-400 bg-green-500/10 border-green-500/30',
            'caution' => 'text-yellow-400 bg-yellow-500/10 border-yellow-500/30',
            'danger' => 'text-red-400 bg-red-500/10 border-red-500/30',
            default => 'text-dark-400 bg-dark-800/50 border-dark-700 hover:border-dark-600',
        };
    }

    /**
     * Get formatted time for display.
     */
    public function getFormattedTimeAttribute(): ?string
    {
        if (!$this->time) {
            return 'All Day';
        }
        
        try {
            return Carbon::parse($this->time)->format('H:i');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get formatted date (YYYY-MM-DD) for display.
     */
    public function getFormattedDateAttribute(): ?string
    {
        if (!$this->date) {
            return null;
        }

        try {
            return $this->date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get disable window description.
     */
    public function getDisableWindowAttribute(): ?string
    {
        if (!$this->should_disable_ea || !$this->time) {
            return null;
        }

        try {
            $eventTime = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->time->format('H:i:s'));
            $start = $eventTime->copy()->subMinutes($this->disable_minutes_before)->format('H:i');
            $end = $eventTime->copy()->addMinutes($this->disable_minutes_after)->format('H:i');
            return "{$start} - {$end}";
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if EA should be disabled at given time.
     */
    public function shouldDisableEaAt(Carbon $checkTime): bool
    {
        if (!$this->should_disable_ea || !$this->time) {
            return false;
        }

        try {
            $eventTime = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->time->format('H:i:s'));
            $windowStart = $eventTime->copy()->subMinutes($this->disable_minutes_before);
            $windowEnd = $eventTime->copy()->addMinutes($this->disable_minutes_after);
            
            return $checkTime->between($windowStart, $windowEnd);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Scope for a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Scope for a specific currency.
     */
    public function scopeForCurrency($query, string $currency)
    {
        return $query->where('currency', strtoupper($currency));
    }

    /**
     * Scope for high impact news.
     */
    public function scopeHighImpact($query)
    {
        return $query->where('impact', 'high');
    }

    /**
     * Scope for today's news.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    /**
     * Scope for upcoming news.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('time');
    }

    /**
     * Scope for news that should disable EA.
     */
    public function scopeShouldDisableEa($query)
    {
        return $query->where('should_disable_ea', true);
    }

    /**
     * Scope for danger status news.
     */
    public function scopeDanger($query)
    {
        return $query->where('ea_status', 'danger');
    }

    /**
     * Scope for safe status news.
     */
    public function scopeSafe($query)
    {
        return $query->where('ea_status', 'safe');
    }

    /**
     * Scope for news affecting specific pair.
     */
    public function scopeAffectsPair($query, string $pair)
    {
        return $query->whereJsonContains('affected_pairs', strtoupper($pair));
    }
}
