<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AchievementController extends Controller
{
    public function __construct(private readonly LeaderboardService $leaderboardService) {}

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
     * CACHING BEHAVIOR (by design) — see App\Services\LeaderboardService for the
     * full contract:
     * - Results are cached with TTL from config/cache_ttl.php (default 300s = 5 min)
     * - Caching is TTL-only; there is no active/tag-based invalidation, since
     *   tagged caching is unsupported on the database/file/array drivers.
     * - Results may be stale by up to TTL seconds; this is an intentional tradeoff for performance
     * - Real-time accuracy is NOT a requirement; eventual consistency is acceptable
     *
     * CLIENT EXPECTATIONS:
     * - Rank may be off by a few positions if XP updates occurred in the past seconds
     * - Refreshes naturally within TTL; no manual cache busting required
     * - If real-time ranking is critical, client must poll with short intervals
     *
     * See: PRODUCTION_SCALE_FIXES_GUIDE.md "Leaderboard Cache Freshness Contract"
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 10)));

        $paginator = $this->leaderboardService->getPage($page, $perPage);

        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())->values()->map(function ($user, $index) use ($paginator) {
                return [
                    'rank' => $this->leaderboardService->computeRank($paginator, $index),
                    'name' => $user->name,
                    'level' => $user->level,
                    'experience_points' => $user->experience_points,
                ];
            }),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }
}
