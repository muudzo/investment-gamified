<?php
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