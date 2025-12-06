<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i:s',
        'is_manual' => 'boolean',
    ];

    /**
     * Get the user who created this news item.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
            'medium' => '🟡',
            'low' => '🟢',
            default => '⚪',
        };
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
}

