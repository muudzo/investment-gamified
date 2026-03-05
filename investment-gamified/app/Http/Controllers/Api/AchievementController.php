<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AchievementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // LEFT JOIN to compute 'unlocked' in a single query avoiding O(n^2) in_array checks
        $achievements = Achievement::leftJoin('achievement_user as au', function ($join) use ($user) {
                $join->on('achievements.id', '=', 'au.achievement_id')
                     ->where('au.user_id', $user->id);
            })
            ->select('achievements.*', DB::raw('CASE WHEN au.user_id IS NOT NULL THEN 1 ELSE 0 END as unlocked'))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $achievements->map(function ($achievement) {
                return [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'icon' => $achievement->icon,
                    'xp_reward' => $achievement->xp_reward,
                    'unlocked' => (bool) $achievement->unlocked,
                ];
            }),
        ]);
    }

    /**
     * Get paginated leaderboard ranked by level, experience points, and user ID (tie-breaker).
     * 
     * CACHING BEHAVIOR (by design):
     * - Leaderboard results are cached with TTL from config/cache_ttl.php (default 300s = 5 min)
     * - Cache invalidated ONLY when a user's XP/level changes (during buy/sell)
     * - Results may be stale by up to TTL seconds; this is an intentional tradeoff for performance
     * - Real-time accuracy is NOT a requirement; eventual consistency is acceptable
     * 
     * WHY CACHE?
     * - Full leaderboard query (ORDER BY level DESC, xp DESC) is expensive on large user bases
     * - Pagination reduces per-request cost but still requires full sort before slice
     * - Cache prevents thrashing DB with repeated expensive queries
     * 
     * CLIENT EXPECTATIONS:
     * - Rank may be off by a few positions if XP updates occurred in the past seconds
     * - Refreshes naturally within TTL; no manual cache busting required
     * - If real-time ranking is critical, client must poll with short intervals
     * 
     * OPERATIONS NOTE:
     * - Monitor cache hit ratio; if <70%, investigate load patterns or reduce TTL
     * - Cache tags are used for invalidation; tagged flushing requires tag-aware driver (Redis)
     * 
     * See: PRODUCTION_SCALE_FIXES_GUIDE.md "Leaderboard Cache Freshness Contract"
     */
    public function leaderboard()
    {
        $page = max(1, (int) request()->query('page', 1));
        $perPage = min(100, max(1, (int) request()->query('per_page', 10)));

        $cacheKey = "leaderboard_page_{$page}_{$perPage}";
        $ttl = config('cache_ttl.leaderboard', 300);

        $paginator = Cache::tags(['leaderboard'])->remember($cacheKey, $ttl, function () use ($perPage, $page) {
            return User::orderBy('level', 'desc')
                ->orderBy('experience_points', 'desc')
                ->orderBy('id', 'asc')
                ->paginate($perPage, ['id', 'name', 'level', 'experience_points'], 'page', $page);
        });

        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())->values()->map(function ($user, $index) use ($paginator) {
                // compute absolute rank
                $rank = ($paginator->currentPage() - 1) * $paginator->perPage() + $index + 1;
                return [
                    'rank' => $rank,
                    'name' => $user['name'],
                    'level' => $user['level'],
                    'experience_points' => $user['experience_points'],
                ];
            }),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }
}
