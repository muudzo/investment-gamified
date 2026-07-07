<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Encapsulates paginated leaderboard reads.
 *
 * DRIVER-AGNOSTIC CACHING:
 * - Uses plain Cache::remember() (no tags). Tagged caching is only supported by
 *   the redis/memcached drivers; the database/file/array drivers throw a
 *   BadMethodCallException if Cache::tags() is called. Since this application
 *   must work under any configured cache driver, tags are never used here.
 *
 * INVALIDATION CONTRACT (TTL-ONLY):
 * - There is no active invalidation. Nothing flushes or tags this cache when a
 *   user's level/XP changes.
 * - Freshness is bounded solely by config('cache_ttl.leaderboard', 300) seconds.
 *   Staleness up to that TTL is expected and acceptable (see
 *   PRODUCTION_SCALE_FIXES_GUIDE.md "Leaderboard Cache Freshness Contract").
 * - Do not reintroduce tag-based or event-based invalidation without also
 *   guaranteeing a tag-capable cache driver in every environment.
 */
class LeaderboardService
{
    /**
     * Fetch a paginated, ranked slice of the leaderboard.
     *
     * Ordering: level desc, experience_points desc, id asc (stable tie-breaker).
     * Cache key is namespaced by page + perPage so each page/size combination
     * is cached independently.
     */
    public function getPage(int $page, int $perPage): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $cacheKey = "leaderboard_page_{$page}_{$perPage}";
        $ttl = (int) config('cache_ttl.leaderboard', 300);

        return Cache::remember($cacheKey, $ttl, function () use ($page, $perPage) {
            return User::orderBy('level', 'desc')
                ->orderBy('experience_points', 'desc')
                ->orderBy('id', 'asc')
                ->paginate($perPage, ['id', 'name', 'level', 'experience_points'], 'page', $page);
        });
    }

    /**
     * Compute the absolute (1-based) rank of an item at $indexOnPage within the
     * given paginator's current page.
     */
    public function computeRank(LengthAwarePaginator $paginator, int $indexOnPage): int
    {
        return ($paginator->currentPage() - 1) * $paginator->perPage() + $indexOnPage + 1;
    }
}
