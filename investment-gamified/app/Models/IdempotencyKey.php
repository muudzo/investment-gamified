<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persisted record of an idempotent request.
 *
 * One row per (user_id, idempotency_key). While a request is in flight the row
 * carries a fresh `locked_at` and null response columns. Once the handler
 * produces a terminal (non-5xx) response, the status/body are stored and the
 * lock is cleared so subsequent duplicates can replay the exact same result.
 */
class IdempotencyKey extends Model
{
    protected $fillable = [
        'user_id',
        'idempotency_key',
        'request_hash',
        'response_status',
        'response_body',
        'locked_at',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
