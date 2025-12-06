<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'trade_id',
        'entry_date',
        'entry_type',
        'title',
        'content',
        'mood',
        'tags',
        'attachments',
        'is_public',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'tags' => 'array',
        'attachments' => 'array',
        'is_public' => 'boolean',
    ];

    /**
     * Get the user that owns this entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the account this entry is linked to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the trade this entry is linked to.
     */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /**
     * Get mood emoji.
     */
    public function getMoodEmojiAttribute(): string
    {
        return match($this->mood) {
            'confident' => '😊',
            'neutral' => '😐',
            'anxious' => '😰',
            'frustrated' => '😤',
            default => '📝',
        };
    }

    /**
     * Get mood color class.
     */
    public function getMoodColorAttribute(): string
    {
        return match($this->mood) {
            'confident' => 'text-green-500',
            'neutral' => 'text-gray-500',
            'anxious' => 'text-yellow-500',
            'frustrated' => 'text-red-500',
            default => 'text-blue-500',
        };
    }

    /**
     * Scope for a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('entry_date', $date);
    }

    /**
     * Scope for a specific entry type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('entry_type', $type);
    }

    /**
     * Scope for public entries.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}

