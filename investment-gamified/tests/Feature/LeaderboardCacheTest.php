<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression test for the leaderboard cache-tags bug (C1).
 *
 * AchievementController::leaderboard() used to call
 * Cache::tags(['leaderboard'])->remember(...). The database/file/array cache
 * stores do not support tags, so that call threw a BadMethodCallException on
 * every request under any non-tag-aware driver. The test suite previously
 * only exercised the 'array' store (see phpunit.xml CACHE_STORE=array),
 * which happens to support tags, masking the bug in CI.
 *
 * This test explicitly runs the request under the 'database' cache store
 * (the app's actual configured default per config/cache.php) to prove the
 * bug is gone and the leaderboard responds 200 with stable pagination
 * metadata, in addition to covering the 'array' store used by the rest of
 * the suite.
 */
class LeaderboardCacheTest extends TestCase
{
    public static function cacheStoreProvider(): array
    {
        return [
            'array cache store' => ['array'],
            'database cache store' => ['database'],
        ];
    }

    #[DataProvider('cacheStoreProvider')]
    public function test_leaderboard_is_cached_and_returns_pages(string $cacheStore): void
    {
        config(['cache.default' => $cacheStore]);
        Cache::flush();

        Sanctum::actingAs(User::factory()->create());

        User::factory()->count(30)->create();

        $response = $this->getJson('/api/leaderboard?page=1&per_page=10');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.current_page', 1);

        // Second request should be served from cache (no tag-related crash)
        // and report the same, stable pagination metadata.
        $response2 = $this->getJson('/api/leaderboard?page=1&per_page=10');
        $response2->assertStatus(200)
            ->assertJsonPath('meta.per_page', $response->json('meta.per_page'))
            ->assertJsonPath('meta.current_page', $response->json('meta.current_page'))
            ->assertJsonPath('meta.total', $response->json('meta.total'))
            ->assertJsonPath('meta.last_page', $response->json('meta.last_page'));
    }
}
