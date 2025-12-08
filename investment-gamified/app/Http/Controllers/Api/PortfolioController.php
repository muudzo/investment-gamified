<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PortfolioService;
use App\Models\Portfolio;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    protected $portfolioService;

    public function __construct(PortfolioService $portfolioService)
    {
        $this->portfolioService = $portfolioService;
    }

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
                'name' => $user->name,
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

        $result = $this->portfolioService->buyStock($user, $validated['stock_symbol'], $validated['quantity']);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'new_balance' => $user->fresh()->balance,
                'xp_earned' => $result['data']['xp_earned'] ?? null,
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

        $result = $this->portfolioService->sellStock($user, $validated['stock_symbol'], $validated['quantity']);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'new_balance' => $user->fresh()->balance,
                'xp_earned' => $result['data']['xp_earned'] ?? null,
            ]
        ]);
    }
}
