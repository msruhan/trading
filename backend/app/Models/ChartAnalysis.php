<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChartAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'symbol',
        'interval',
        'title',
        'notes',
        'drawings',
        'tradingview_state',
    ];

    protected $casts = [
        'drawings' => 'array',
        'tradingview_state' => 'array',
    ];

    /**
     * Get the user that owns this analysis.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

