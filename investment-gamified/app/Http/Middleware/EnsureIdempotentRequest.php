<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees at-most-once execution of unsafe, user-scoped requests.
 *
 * Correctness rests on a single atomic INSERT against a UNIQUE(user_id,
 * idempotency_key) constraint: exactly one concurrent caller wins and runs the
 * handler; every colliding caller replays the stored result, is told the
 * request is still in flight (409), or is rejected for reusing a key with a
 * different payload (422).
 */
class EnsureIdempotentRequest
{
    /** Bounds for an acceptable client-supplied key token. */
    private const KEY_MIN_LENGTH = 8;

    private const KEY_MAX_LENGTH = 255;

    private const KEY_PATTERN = '/^[A-Za-z0-9._\-]+$/';

    /** Responses at or above this status are treated as non-terminal (retryable). */
    private const SERVER_ERROR_THRESHOLD = 500;

    public function handle(Request $request, Closure $next): Response
    {
        $config = config('idempotency');

        // Safe methods are never guarded — pass straight through.
        if (! in_array($request->getMethod(), $config['methods'], true)) {
            return $next($request);
        }

        $key = trim((string) $request->headers->get($config['header'], ''));

        if ($key === '') {
            return $this->error(400, 'Idempotency-Key header is required for this request.');
        }

        if (! $this->keyIsValid($key)) {
            return $this->error(400, 'Idempotency-Key is malformed.');
        }

        // Idempotency is user-scoped. Without an authenticated user we defer to
        // the auth middleware to reject the request.
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $hash = $this->hashRequest($request);
        $record = $this->claim((int) $user->getAuthIdentifier(), $key, $hash);

        // Won the atomic insert: we are the first (and only) executor.
        if ($record !== null) {
            return $this->execute($record, $request, $next);
        }

        return $this->resolveDuplicate((int) $user->getAuthIdentifier(), $key, $hash, $request, $next);
    }

    /**
     * Attempt the atomic first-writer-wins claim.
     *
     * Returns the freshly-locked record when this caller wins the insert, or
     * null when the unique constraint rejects it as a duplicate.
     */
    private function claim(int $userId, string $key, string $hash): ?IdempotencyKey
    {
        try {
            return DB::transaction(fn (): IdempotencyKey => IdempotencyKey::create([
                'user_id' => $userId,
                'idempotency_key' => $key,
                'request_hash' => $hash,
                'response_status' => null,
                'response_body' => null,
                'locked_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * Decide how a colliding duplicate request is handled.
     */
    private function resolveDuplicate(int $userId, string $key, string $hash, Request $request, Closure $next): Response
    {
        $existing = IdempotencyKey::query()
            ->where('user_id', $userId)
            ->where('idempotency_key', $key)
            ->first();

        // Lost a race with the original insert and the row vanished (e.g. a 5xx
        // cleared it). Safest correct action is to reject as in-flight/retryable.
        if ($existing === null) {
            return $this->error(409, 'A request with this Idempotency-Key is already being processed.');
        }

        if ($existing->request_hash !== $hash) {
            return $this->error(422, 'Idempotency-Key was already used with a different request payload.');
        }

        if ($existing->response_status !== null) {
            return $this->replay($existing);
        }

        if ($this->lockIsFresh($existing)) {
            return $this->error(409, 'A request with this Idempotency-Key is already being processed.');
        }

        // Stale lock: the original executor never completed — reclaim and re-run.
        return $this->execute($existing, $request, $next);
    }

    /**
     * Run the handler, then persist a terminal response or clear the lock so a
     * server error can be retried. Shared by the first-caller and reclaim paths.
     */
    private function execute(IdempotencyKey $record, Request $request, Closure $next): Response
    {
        $record->forceFill(['locked_at' => now()])->save();

        $response = $next($request);
        $status = $response->getStatusCode();

        if ($status >= self::SERVER_ERROR_THRESHOLD) {
            // Non-terminal: drop the row so the client may retry cleanly.
            $record->delete();

            return $response;
        }

        $record->forceFill([
            'response_status' => $status,
            'response_body' => $response->getContent(),
            'locked_at' => null,
        ])->save();

        return $response;
    }

    /**
     * Reconstruct the stored response for a completed duplicate request.
     */
    private function replay(IdempotencyKey $record): Response
    {
        return response(
            (string) $record->response_body,
            (int) $record->response_status,
            [
                'Content-Type' => 'application/json',
                'Idempotency-Replayed' => 'true',
            ],
        );
    }

    private function lockIsFresh(IdempotencyKey $record): bool
    {
        if ($record->locked_at === null) {
            return false;
        }

        $timeout = (int) config('idempotency.lock_timeout_seconds');

        return $record->locked_at->greaterThan(now()->subSeconds($timeout));
    }

    private function keyIsValid(string $key): bool
    {
        $length = strlen($key);

        if ($length < self::KEY_MIN_LENGTH || $length > self::KEY_MAX_LENGTH) {
            return false;
        }

        return preg_match(self::KEY_PATTERN, $key) === 1;
    }

    private function hashRequest(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->getMethod(),
            $request->getPathInfo(),
            $request->getContent(),
        ]));
    }

    private function error(int $status, string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
