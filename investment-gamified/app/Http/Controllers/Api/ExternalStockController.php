<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockApiService;
use App\Services\FinancialModelingPrepService;
use Illuminate\Http\Request;

class ExternalStockController extends Controller
{
    protected StockApiService $alphaService;
    protected FinancialModelingPrepService $fmpService;

    public function __construct(StockApiService $alphaService, FinancialModelingPrepService $fmpService)
    {
        $this->alphaService = $alphaService;
        $this->fmpService = $fmpService;
    }

    /**
     * GET /api/external/stocks/quote/{symbol}?source=alphavantage|fmp
     */
    public function quote(Request $request, string $symbol)
    {
        $source = strtolower($request->query('source', 'alphavantage'));

        if ($source === 'fmp') {
            $data = $this->fmpService->getQuote($symbol);
        } else {
            $data = $this->alphaService->getQuote($symbol);
        }

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'No data returned from provider'], 502);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/external/stocks/history/{symbol}?source=alphavantage|fmp&days=30
     */
    public function history(Request $request, string $symbol)
    {
        $source = strtolower($request->query('source', 'alphavantage'));
        $days = (int) $request->query('days', 30);

        if ($source === 'fmp') {
            // fmp returns history as array of records
            $data = $this->fmpService->getHistoricalPrices($symbol, $days);
        } else {
            $data = $this->alphaService->getHistoricalData($symbol, 'compact');
        }

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'No history available'], 502);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/external/stocks/search?q=apple&source=alphavantage|fmp
     */
    public function search(Request $request)
    {
        $query = $request->query('q');
        $source = strtolower($request->query('source', 'alphavantage'));

        if (!$query) {
            return response()->json(['success' => false, 'message' => 'Query parameter "q" is required'], 422);
        }

        if ($source === 'fmp') {
            $data = $this->fmpService->searchStocks($query);
        } else {
            $data = $this->alphaService->searchStocks($query);
        }

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'No results from provider'], 502);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/external/stocks/profile/{symbol}   (FMP only)
     */
    public function profile(string $symbol)
    {
        $data = $this->fmpService->getCompanyProfile($symbol);

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'No profile data available'], 502);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}
