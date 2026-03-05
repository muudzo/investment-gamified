<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\StockHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50',
            'category' => 'string|nullable',
            'search' => 'string|nullable|min:2|max:50',
        ]);

        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 20);

        $query = Stock::query();

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('search')) {
            $q = $request->query('search');
            // Use LIKE fallback if fulltext not available
            $query->where(function ($qbuilder) use ($q) {
                $qbuilder->where('name', 'like', "%{$q}%")
                    ->orWhere('symbol', 'like', "%{$q}%");
            });
        }

        $stocks = $query->select('symbol', 'name', 'current_price', 'change_percentage', 'category', 'description', 'kid_friendly_description')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data'    => $stocks->items(),
            'meta'    => $this->paginationMeta($stocks),
        ]);
    }

    public function show(string $symbol): JsonResponse
    {
        $stock = Stock::where('symbol', $symbol)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $this->transformStock($stock, true),
        ]);
    }

    public function history(string $symbol, Request $request): JsonResponse
    {
        $stock = Stock::where('symbol', $symbol)->firstOrFail();
        $days = (int) $request->input('days', 30);

        $history = StockHistory::where('stock_id', $stock->id)
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date', 'asc')
            ->get(['date', 'close_price']);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    private function transformStock(Stock $stock, bool $withFunFact): array
    {
        $payload = [
            'symbol' => $stock->symbol,
            'name' => $stock->name,
            'current_price' => $stock->current_price,
            'change_percentage' => $stock->change_percentage,
            'category' => $stock->category,
            'description' => $stock->description,
            'kid_friendly_description' => $stock->kid_friendly_description,
        ];

        if ($withFunFact) {
            $payload['fun_fact'] = $stock->fun_fact;
        }

        return $payload;
    }
}
