<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'status',
        'source',
        'message',
        'payload',
        'stats',
        'started_at',
        'completed_at',
        'duration_ms',
    ];

    protected $casts = [
        'payload' => 'array',
        'stats' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the account that owns this sync log.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Start the sync process.
     */
    public function start(): void
    {
        $this->status = 'processing';
        $this->started_at = now();
        $this->save();
    }

    /**
     * Mark sync as successful.
     */
    public function succeed(string $message = null, int $newTrades = 0, int $updatedTrades = 0): void
    {
        $this->status = 'success';
        $this->message = $message;
        $this->stats = [
            'new_trades' => $newTrades,
            'updated_trades' => $updatedTrades,
            'trades_synced' => $newTrades + $updatedTrades,
        ];
        $this->completed_at = now();
        $this->duration_ms = $this->started_at 
            ? $this->started_at->diffInMilliseconds($this->completed_at) 
            : null;
        $this->save();
    }

    /**
     * Mark sync as failed.
     */
    public function fail(string $message): void
    {
        $this->status = 'failed';
        $this->message = $message;
        $this->completed_at = now();
        $this->duration_ms = $this->started_at 
            ? $this->started_at->diffInMilliseconds($this->completed_at) 
            : null;
        $this->save();
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration_ms) {
            return '-';
        }

        if ($this->duration_ms < 1000) {
            return $this->duration_ms . 'ms';
        }

        $seconds = round($this->duration_ms / 1000, 1);
        return $seconds . 's';
    }

    /**
     * Scope for successful syncs.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed syncs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for recent syncs.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}

