<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EaCommand extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'command',
        'params',
        'status',
        'result',
        'error_message',
        'executed_at',
    ];

    protected $casts = [
        'params' => 'array',
        'executed_at' => 'datetime',
    ];

    /**
     * Get the account that owns this command.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Mark command as executing.
     */
    public function markExecuting(): void
    {
        $this->update([
            'status' => 'executing',
        ]);
    }

    /**
     * Mark command as completed.
     */
    public function markCompleted(?string $result = null): void
    {
        $this->update([
            'status' => 'completed',
            'result' => $result,
            'executed_at' => now(),
        ]);
    }

    /**
     * Mark command as failed.
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'executed_at' => now(),
        ]);
    }

    /**
     * Scope to get pending commands.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get commands for an account.
     */
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }
}

