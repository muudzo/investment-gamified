<?php

declare(strict_types=1);

/**
 * Idempotency Configuration
 *
 * Central home for every idempotency tuning knob. The middleware reads only
 * from here — no magic numbers live in the request path.
 *
 * CORRECTNESS NOTES:
 * - `methods`: only unsafe, state-changing verbs need idempotency guards.
 * - `ttl_hours`: how long a stored response may be replayed before the key is
 *   considered expired and eligible for pruning (see console pruning command).
 * - `lock_timeout_seconds`: an in-flight lock older than this is assumed to
 *   belong to a crashed/aborted request and is reclaimed by the next caller.
 *   Set this comfortably above the worst-case trade handler latency.
 */
return [
    // Header clients send to identify a logically-unique request.
    'header' => 'Idempotency-Key',

    // HTTP methods that require an idempotency key. Safe methods pass through.
    'methods' => ['POST'],

    // How long (hours) a completed key/response is retained and replayable.
    'ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),

    // Age (seconds) beyond which an in-flight lock is treated as stale and
    // reclaimable. Keep above the slowest expected handler runtime.
    'lock_timeout_seconds' => (int) env('IDEMPOTENCY_LOCK_TIMEOUT_SECONDS', 30),
];
