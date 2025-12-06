<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'log_type',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->created_at ?? now();
        });
    }

    /**
     * Get the user that performed the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subject of the activity.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Create a new activity log.
     */
    public static function log(
        string $logType,
        string $action,
        string $description = null,
        Model $subject = null,
        array $properties = []
    ): self {
        return self::create([
            'user_id' => auth()->id(),
            'log_type' => $logType,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Log auth activity.
     */
    public static function logAuth(string $action, string $description = null): self
    {
        return self::log('auth', $action, $description);
    }

    /**
     * Log sync activity.
     */
    public static function logSync(string $action, Account $account, string $description = null, array $properties = []): self
    {
        return self::log('sync', $action, $description, $account, $properties);
    }

    /**
     * Log trade activity.
     */
    public static function logTrade(string $action, Trade $trade, string $description = null): self
    {
        return self::log('trade', $action, $description, $trade);
    }

    /**
     * Scope by log type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('log_type', $type);
    }

    /**
     * Scope for recent logs.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}

