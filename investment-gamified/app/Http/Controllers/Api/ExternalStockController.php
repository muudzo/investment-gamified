<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\External\StockSymbolRequest;
use App\Models\Stock;
use App\Services\ExternalStockProvider;
use App\Services\FinancialModelingPrepService;
use App\Services\StockApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalStockController extends Controller
{
    private const MIN_HISTORY_DAYS = 1;

    private const MAX_HISTORY_DAYS = 365;

    private const MIN_QUERY_LENGTH = 1;

    private const MAX_QUERY_LENGTH = 50;

    public function __construct(
        private readonly StockApiService $alphaService,
        private readonly FinancialModelingPrepService $fmpService,
    ) {}

    /**
     * GET /api/external/stocks/quote/{symbol}?source=alphavantage|fmp
     */
    public function quote(StockSymbolRequest $request): JsonResponse
    {
        $symbol = $request->symbol();

        if ($notFound = $this->ensureSymbolExists($symbol)) {
            return $notFound;
        }

        try {
            $data = $this->resolveProvider($request)->getQuote($symbol);
        } catch (\Exception $e) {
            return $this->externalApiErrorResponse($e->getMessage());
        }

        return $this->providerDataResponse($data, 'No data returned from provider');
    }

    /**
     * GET /api/external/stocks/history/{symbol}?source=alphavantage|fmp&days=30
     */
    public function history(StockSymbolRequest $request): JsonResponse
    {
        $symbol = $request->symbol();

        if ($notFound = $this->ensureSymbolExists($symbol)) {
            return $notFound;
        }

        $days = $this->normalizeDays($request->query('days', 30));

        try {
            $data = $this->resolveProvider($request)->getHistoricalPrices($symbol, $days);
        } catch (\Exception $e) {
            return $this->externalApiErrorResponse($e->getMessage());
        }

        return $this->providerDataResponse($data, 'No history available');
    }

    /**
     * GET /api/external/stocks/search?q=apple&source=alphavantage|fmp
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['success' => false, 'message' => 'Query parameter "q" is required'], 422);
        }

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH || mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter "q" must be between '.self::MIN_QUERY_LENGTH.' and '.self::MAX_QUERY_LENGTH.' characters',
            ], 422);
        }

        try {
            $data = $this->resolveProvider($request)->searchStocks($query);
        } catch (\Exception $e) {
            return $this->externalApiErrorResponse($e->getMessage());
        }

        return $this->providerDataResponse($data, 'No results from provider');
    }

    /**
     * GET /api/external/stocks/profile/{symbol}   (FMP only)
     */
    public function profile(StockSymbolRequest $request): JsonResponse
    {
        $symbol = $request->symbol();

        if ($notFound = $this->ensureSymbolExists($symbol)) {
            return $notFound;
        }

        try {
            $data = $this->fmpService->getCompanyProfile($symbol);
        } catch (\Exception $e) {
            return $this->externalApiErrorResponse($e->getMessage());
        }

        return $this->providerDataResponse($data, 'No profile data available');
    }

    /**
     * Guards the route-parameter symbols against values that pass format
     * validation but aren't tradable locally.
     */
    private function ensureSymbolExists(string $symbol): ?JsonResponse
    {
        if (! Stock::where('symbol', $symbol)->exists()) {
            return response()->json(['success' => false, 'message' => 'Symbol not available for trading'], 404);
        }

        return null;
    }

    private function normalizeDays(mixed $days): int
    {
        $days = filter_var($days, FILTER_VALIDATE_INT) ?: self::MIN_HISTORY_DAYS;

        return max(self::MIN_HISTORY_DAYS, min(self::MAX_HISTORY_DAYS, $days));
    }

    private function resolveProvider(Request $request): ExternalStockProvider
    {
        return strtolower((string) $request->query('source', 'alphavantage')) === 'fmp'
            ? $this->fmpService
            : $this->alphaService;
    }

    private function externalApiErrorResponse(string $error): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'External API error: '.$error,
        ], 503);
    }

    private function providerDataResponse(?array $data, string $emptyMessage): JsonResponse
    {
        if (! $data) {
            return response()->json(['success' => false, 'message' => $emptyMessage], 502);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}
