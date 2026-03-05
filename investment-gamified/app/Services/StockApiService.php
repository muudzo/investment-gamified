<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StockApiService implements ExternalStockProvider
{
    private const BASE_URL = 'https://www.alphavantage.co/query';

    private ?string $apiKey;
    private CircuitBreaker $circuit;
    private ApiQuotaTracker $quota;

    public function __construct()
    {
        $this->apiKey  = config('services.alphavantage.key');
        $this->circuit = new CircuitBreaker('alphavantage');
        $this->quota   = new ApiQuotaTracker();
    }

    public function getQuote(string $symbol): ?array
    {
        $cacheKey = "stock_quote_{$symbol}";
        $stale    = Cache::get($cacheKey);

        return $this->circuit->call(function () use ($symbol, $cacheKey) {
            if (!$this->quota->hasQuota('alphavantage')) {
                Log::warning('AlphaVantage quota exhausted, returning stale cache if available');
                return Cache::get($cacheKey);
            }

            $this->quota->recordRequest('alphavantage');

            return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($symbol) {
                $data = $this->fetchData([
                    'function' => 'GLOBAL_QUOTE',
                    'symbol'   => $symbol,
                    'apikey'   => $this->apiKey,
                ], 5, "Failed to fetch quote for {$symbol}");

                if (!isset($data['Global Quote'])) {
                    throw new \RuntimeException('AlphaVantage unexpected response');
                }

                $quote = $data['Global Quote'];

                return [
                    'symbol'         => $quote['01. symbol'] ?? null,
                    'price'          => $quote['05. price'] ?? null,
                    'change'         => $quote['09. change'] ?? null,
                    'change_percent' => $quote['10. change percent'] ?? null,
                ];
            });
        }, fn () => $stale ?? null);
    }

    public function getHistoricalPrices(string $symbol, int $days = 30): ?array
    {
        $outputSize = $days > 100 ? 'full' : 'compact';
        $cacheKey   = "stock_history_{$symbol}_{$outputSize}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($symbol, $outputSize): ?array {
            $data = $this->fetchData([
                'function'   => 'TIME_SERIES_DAILY',
                'symbol'     => $symbol,
                'outputsize' => $outputSize,
                'apikey'     => $this->apiKey,
            ], 10, "Failed to fetch history for {$symbol}");

            return $data['Time Series (Daily)'] ?? null;
        });
    }

    public function searchStocks(string $query): ?array
    {
        $data = $this->fetchData([
            'function' => 'SYMBOL_SEARCH',
            'keywords' => $query,
            'apikey'   => $this->apiKey,
        ], 5, 'Failed to search stocks');

        return $data['bestMatches'] ?? null;
    }

    public function getCompanyProfile(string $symbol): ?array
    {
        $cacheKey = "company_overview_{$symbol}";

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($symbol): ?array {
            return $this->fetchData([
                'function' => 'OVERVIEW',
                'symbol'   => $symbol,
                'apikey'   => $this->apiKey,
            ], 5, "Failed to fetch overview for {$symbol}");
        });
    }

    private function fetchData(array $params, int $timeoutSeconds, string $errorMessage): ?array
    {
        try {
            $response = Http::timeout($timeoutSeconds)
                ->retry(3, 100)
                ->get(self::BASE_URL, $params);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error($errorMessage . ': ' . $e->getMessage());

            return null;
        }
    }
}
