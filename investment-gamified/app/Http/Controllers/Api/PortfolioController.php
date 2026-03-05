<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portfolio\TradeStockRequest;
use App\Models\Portfolio;
use App\Services\PortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortfolioController extends Controller
{
    public function __construct(private readonly PortfolioService $portfolioService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));

        $paginator = DB::table('portfolios as p')
            ->join('stocks as s', 'p.stock_id', '=', 's.id')
            ->where('p.user_id', $request->user()->id)
            ->where('p.quantity', '>', 0)
            ->select(
                'p.id as portfolio_id',
                's.id as stock_id',
                's.symbol as stock_symbol',
                's.name as stock_name',
                'p.quantity',
                'p.average_price',
                's.current_price',
                DB::raw('p.quantity * s.current_price as total_value'),
                DB::raw('(s.current_price - p.average_price) * p.quantity as profit_loss'),
                DB::raw('CASE WHEN p.average_price = 0 THEN 0 ELSE ((s.current_price - p.average_price) / p.average_price) * 100 END as profit_loss_percentage')
            )
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'meta'    => $this->paginationMeta($paginator),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user      = $request->user();
        $portfolio = Portfolio::where('user_id', $user->id)->with('stock')->get();

        $totalValue    = $portfolio->sum(fn (Portfolio $item): float => $item->quantity * $item->stock->current_price);
        $totalInvested = $portfolio->sum(fn (Portfolio $item): float => $item->quantity * $item->average_price);

        return response()->json([
            'success' => true,
            'data'    => [
                'name'                  => $user->name,
                'balance'               => $user->balance,
                'total_invested'        => $totalInvested,
                'total_value'           => $totalValue,
                'profit_loss'           => $totalValue - $totalInvested,
                'profit_loss_percentage' => $totalInvested > 0
                    ? (($totalValue - $totalInvested) / $totalInvested) * 100
                    : 0,
                'level'            => $user->level,
                'experience_points' => $user->experience_points,
                'next_level_xp'    => $user->level * 1000,
            ],
        ]);
    }

    public function buyStock(TradeStockRequest $request): JsonResponse
    {
        return $this->handleTrade($request, 'buyStock');
    }

    public function sellStock(TradeStockRequest $request): JsonResponse
    {
        return $this->handleTrade($request, 'sellStock');
    }

    private function handleTrade(TradeStockRequest $request, string $method): JsonResponse
    {
        $validated = $request->validated();
        $user      = $request->user();

        $result = $this->portfolioService->{$method}($user, $validated['stock_symbol'], $validated['quantity']);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => [
                'new_balance' => $user->fresh()->balance,
                'xp_earned'   => $result['data']['xp_earned'] ?? null,
            ],
        ]);
    }
}
