<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Achievement;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $achievements = Achievement::all();
        
        $userAchievements = $user->achievements->pluck('id')->toArray();

        return response()->json([
            'success' => true,
            'data' => $achievements->map(function ($achievement) use ($userAchievements) {
                return [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'icon' => $achievement->icon,
                    'xp_reward' => $achievement->xp_reward,
                    'unlocked' => in_array($achievement->id, $userAchievements),
                ];
            })
        ]);
    }

    public function leaderboard()
    {
        $topUsers = User::orderBy('level', 'desc')
            ->orderBy('experience_points', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'level', 'experience_points']);

        return response()->json([
            'success' => true,
            'data' => $topUsers->map(function ($user, $index) {
                return [
                    'rank' => $index + 1,
                    'name' => $user->name,
                    'level' => $user->level,
                    'experience_points' => $user->experience_points,
                ];
            })
        ]);
    }
}
