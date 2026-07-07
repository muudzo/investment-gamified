<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\EnsureIdempotentRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Exercises EnsureIdempotentRequest in isolation via a throwaway route, since
 * the orchestrator wires the middleware into the real trade routes separately.
 *
 * The handler bumps an in-process counter so we can prove exact-once execution.
 */
class IdempotencyTest extends TestCase
{
    private const ROUTE = '/_idem_test';

    /** Counts how many times the throwaway handler body actually runs. */
    private static int $handlerRuns = 0;

    protected function setUp(): void
    {
        parent::setUp();

        self::$handlerRuns = 0;

        Route::post(self::ROUTE, function (Request $request) {
            self::$handlerRuns++;

            return response()->json([
                'runs' => self::$handlerRuns,
                'echo' => $request->input('payload'),
            ]);
        })->middleware(['auth:sanctum', EnsureIdempotentRequest::class]);
    }

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_same_key_and_body_runs_handler_once_and_replays(): void
    {
        $this->authenticate();
        $headers = ['Idempotency-Key' => 'key-same-0001'];

        $first = $this->withHeaders($headers)->postJson(self::ROUTE, ['payload' => 'buy-aapl']);
        $second = $this->withHeaders($headers)->postJson(self::ROUTE, ['payload' => 'buy-aapl']);

        $first->assertOk();
        $first->assertHeaderMissing('Idempotency-Replayed');

        $second->assertOk();
        $second->assertHeader('Idempotency-Replayed', 'true');

        // The bodies are byte-identical: the second is a stored replay.
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertSame(1, self::$handlerRuns, 'Handler must execute exactly once.');
    }

    public function test_same_key_with_different_body_is_rejected(): void
    {
        $this->authenticate();
        $headers = ['Idempotency-Key' => 'key-diff-0001'];

        $this->withHeaders($headers)->postJson(self::ROUTE, ['payload' => 'buy-aapl'])->assertOk();

        $conflict = $this->withHeaders($headers)->postJson(self::ROUTE, ['payload' => 'sell-tsla']);

        $conflict->assertStatus(422);
        $conflict->assertJson([
            'success' => false,
            'message' => 'Idempotency-Key was already used with a different request payload.',
        ]);
        $this->assertSame(1, self::$handlerRuns, 'Second, mismatched request must not run the handler.');
    }

    public function test_missing_key_is_rejected(): void
    {
        $this->authenticate();

        $response = $this->postJson(self::ROUTE, ['payload' => 'buy-aapl']);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Idempotency-Key header is required for this request.',
        ]);
        $this->assertSame(0, self::$handlerRuns, 'Handler must not run without a valid key.');
    }

    public function test_malformed_key_is_rejected(): void
    {
        $this->authenticate();

        $response = $this->withHeaders(['Idempotency-Key' => 'short'])
            ->postJson(self::ROUTE, ['payload' => 'buy-aapl']);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
        $this->assertSame(0, self::$handlerRuns);
    }

    public function test_different_keys_run_handler_each_time(): void
    {
        $this->authenticate();

        $this->withHeaders(['Idempotency-Key' => 'key-alpha-0001'])
            ->postJson(self::ROUTE, ['payload' => 'buy-aapl'])
            ->assertOk();

        $this->withHeaders(['Idempotency-Key' => 'key-bravo-0002'])
            ->postJson(self::ROUTE, ['payload' => 'buy-aapl'])
            ->assertOk();

        $this->assertSame(2, self::$handlerRuns, 'Distinct keys must each execute the handler.');
    }
}
