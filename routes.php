<?php

// routes/api.php
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\AchievementController;

Route::middleware('auth:sanctum')->group(function () {
    // Portfolio endpoints
    Route::get('/portfolio', [PortfolioController::class, 'index']);
    Route::get('/portfolio/summary', [PortfolioController::class, 'summary']);
    Route::post('/portfolio/buy', [PortfolioController::class, 'buyStock']);
    Route::post('/portfolio/sell', [PortfolioController::class, 'sellStock']);
    
    // Stock market endpoints
    Route::get('/stocks', [StockController::class, 'index']);
    Route::get('/stocks/{symbol}', [StockController::class, 'show']);
    Route::get('/stocks/{symbol}/history', [StockController::class, 'history']);
    
    // Gamification endpoints
    Route::get('/achievements', [AchievementController::class, 'index']);
    Route::get('/leaderboard', [AchievementController::class, 'leaderboard']);
});

// app/Http/Controllers/Api/PortfolioController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortfolioController extends Controller
{
    public function index(Request $request)
    {
        $portfolio = Portfolio::with('stock')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $portfolio->map(function ($item) {
                return [
                    'stock_symbol' => $item->stock->symbol,
                    'stock_name' => $item->stock->name,
                    'quantity' => $item->quantity,
                    'average_price' => $item->average_price,
                    'current_price' => $item->stock->current_price,
                    'total_value' => $item->quantity * $item->stock->current_price,
                    'profit_loss' => ($item->stock->current_price - $item->average_price) * $item->quantity,
                    'profit_loss_percentage' => (($item->stock->current_price - $item->average_price) / $item->average_price) * 100,
                ];
            })
        ]);
    }

    public function summary(Request $request)
    {
        $user = $request->user();
        $portfolio = Portfolio::where('user_id', $user->id)->with('stock')->get();
        
        $totalValue = $portfolio->sum(function ($item) {
            return $item->quantity * $item->stock->current_price;
        });
        
        $totalInvested = $portfolio->sum(function ($item) {
            return $item->quantity * $item->average_price;
        });
        
        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $user->balance,
                'total_invested' => $totalInvested,
                'total_value' => $totalValue,
                'profit_loss' => $totalValue - $totalInvested,
                'profit_loss_percentage' => $totalInvested > 0 ? (($totalValue - $totalInvested) / $totalInvested) * 100 : 0,
                'level' => $user->level,
                'experience_points' => $user->experience_points,
                'next_level_xp' => $user->level * 1000,
            ]
        ]);
    }

    public function buyStock(Request $request)
    {
        $validated = $request->validate([
            'stock_symbol' => 'required|exists:stocks,symbol',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $stock = Stock::where('symbol', $validated['stock_symbol'])->first();
        $totalCost = $stock->current_price * $validated['quantity'];

        if ($user->balance < $totalCost) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance'
            ], 400);
        }

        DB::transaction(function () use ($user, $stock, $validated, $totalCost) {
            // Deduct balance
            $user->balance -= $totalCost;
            $user->save();

            // Update or create portfolio entry
            $portfolio = Portfolio::firstOrNew([
                'user_id' => $user->id,
                'stock_id' => $stock->id,
            ]);

            $newQuantity = $portfolio->quantity + $validated['quantity'];
            $portfolio->average_price = (($portfolio->average_price * $portfolio->quantity) + $totalCost) / $newQuantity;
            $portfolio->quantity = $newQuantity;
            $portfolio->save();

            // Record transaction
            Transaction::create([
                'user_id' => $user->id,
                'stock_id' => $stock->id,
                'type' => 'buy',
                'quantity' => $validated['quantity'],
                'price' => $stock->current_price,
                'total_amount' => $totalCost,
            ]);

            // Award XP
            $user->experience_points += 10;
            if ($user->experience_points >= $user->level * 1000) {
                $user->level++;
                $user->experience_points = 0;
            }
            $user->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock purchased successfully',
            'data' => [
                'new_balance' => $user->fresh()->balance,
                'xp_earned' => 10,
            ]
        ]);
    }

    public function sellStock(Request $request)
    {
        $validated = $request->validate([
            'stock_symbol' => 'required|exists:stocks,symbol',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $stock = Stock::where('symbol', $validated['stock_symbol'])->first();
        
        $portfolio = Portfolio::where('user_id', $user->id)
            ->where('stock_id', $stock->id)
            ->first();

        if (!$portfolio || $portfolio->quantity < $validated['quantity']) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock quantity'
            ], 400);
        }

        $totalRevenue = $stock->current_price * $validated['quantity'];

        DB::transaction(function () use ($user, $stock, $portfolio, $validated, $totalRevenue) {
            // Add to balance
            $user->balance += $totalRevenue;
            $user->save();

            // Update portfolio
            $portfolio->quantity -= $validated['quantity'];
            if ($portfolio->quantity == 0) {
                $portfolio->delete();
            } else {
                $portfolio->save();
            }

            // Record transaction
            Transaction::create([
                'user_id' => $user->id,
                'stock_id' => $stock->id,
                'type' => 'sell',
                'quantity' => $validated['quantity'],
                'price' => $stock->current_price,
                'total_amount' => $totalRevenue,
            ]);

            // Award XP
            $user->experience_points += 15;
            if ($user->experience_points >= $user->level * 1000) {
                $user->level++;
                $user->experience_points = 0;
            }
            $user->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock sold successfully',
            'data' => [
                'new_balance' => $user->fresh()->balance,
                'xp_earned' => 15,
            ]
        ]);
    }
}

// app/Http/Controllers/Api/StockController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\StockHistory;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $stocks = Stock::query()
            ->when($request->category, function ($query, $category) {
                $query->where('category', $category);
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stocks->map(function ($stock) {
                return [
                    'symbol' => $stock->symbol,
                    'name' => $stock->name,
                    'current_price' => $stock->current_price,
                    'change_percentage' => $stock->change_percentage,
                    'category' => $stock->category,
                    'description' => $stock->description,
                    'kid_friendly_description' => $stock->kid_friendly_description,
                ];
            })
        ]);
    }

    public function show($symbol)
    {
        $stock = Stock::where('symbol', $symbol)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'symbol' => $stock->symbol,
                'name' => $stock->name,
                'current_price' => $stock->current_price,
                'change_percentage' => $stock->change_percentage,
                'category' => $stock->category,
                'description' => $stock->description,
                'kid_friendly_description' => $stock->kid_friendly_description,
                'fun_fact' => $stock->fun_fact,
            ]
        ]);
    }

    public function history($symbol, Request $request)
    {
        $stock = Stock::where('symbol', $symbol)->firstOrFail();
        $days = $request->input('days', 30);

        $history = StockHistory::where('stock_id', $stock->id)
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date', 'asc')
            ->get(['date', 'close_price']);

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }
}

// app/Http/Controllers/Api/AchievementController.php
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