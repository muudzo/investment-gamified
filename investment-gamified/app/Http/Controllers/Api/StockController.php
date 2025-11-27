<?php

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
