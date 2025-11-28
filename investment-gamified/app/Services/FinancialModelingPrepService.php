<?php
// app/Services/FinancialModelingPrepService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Financial Modeling Prep API Service
 * Free tier: 250 requests per day
 * NOTE: The free tier does NOT support /quote/{symbol}, only /quote-short/{symbol}
 */
class FinancialModelingPrepService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://financialmodelingprep.com/api/v3';

    public function __construct()
    {
        $this->apiKey = config('services.fmp.key');
    }

    /**
     * Get real-time stock quote (FREE TIER SAFE)
     */
    public function getQuote(string $symbol): ?array
    {
        $cacheKey = "fmp_quote_{$symbol}";
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($symbol) {
            try {
                // FIX: use free-tier /quote-short endpoint
                $response = Http::get("{$this->baseUrl}/quote-short/{$symbol}", [
                    'apikey' => $this->apiKey,
                ]);

                Log::info("FMP Quote response for {$symbol}: " . $response->body());

                if ($response->successful()) {
                    $data = $response->json();

                    if (!empty($data) && isset($data[0])) {
                        $quote = $data[0];
                        return [
                            'symbol'   => $quote['symbol'] ?? null,
                            'price'    => $quote['price'] ?? null,
                            'volume'   => $quote['volume'] ?? null,
                        ];
                    }
                }
                
                return null;
            } catch (\Exception $e) {
                Log::error("FMP ERROR (quote {$symbol}): " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get company profile (FREE TIER SAFE)
     */
    public function getCompanyProfile(string $symbol): ?array
    {
        $cacheKey = "fmp_profile_{$symbol}";
        
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($symbol) {
            try {
                // This endpoint WORKS on free tier
                $response = Http::get("{$this->baseUrl}/profile/{$symbol}", [
                    'apikey' => $this->apiKey,
                ]);

                Log::info("FMP Profile response for {$symbol}: " . $response->body());

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (!empty($data) && isset($data[0])) {
                        return $data[0];
                    }
                }

                return null;
            } catch (\Exception $e) {
                Log::error("FMP ERROR (profile {$symbol}): " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get historical prices (FREE TIER SAFE)
     */
    public function getHistoricalPrices(string $symbol, int $days = 30): ?array
    {
        $cacheKey = "fmp_history_{$symbol}_{$days}";
        
        return Cache::remember($cacheKey, now()->addHours(24), function () use ($symbol, $days) {
            try {
                $from = now()->subDays($days)->format('Y-m-d');
                $to = now()->format('Y-m-d');
                
                $response = Http::get("{$this->baseUrl}/historical-price-full/{$symbol}", [
                    'from'   => $from,
                    'to'     => $to,
                    'apikey' => $this->apiKey,
                ]);

                Log::info("FMP History response for {$symbol}: " . $response->body());

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['historical'])) {
                        return $data['historical'];
                    }
                }

                return null;
            } catch (\Exception $e) {
                Log::error("FMP ERROR (history {$symbol}): " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Search stocks
     */
    public function searchStocks(string $query): ?array
    {
        try {
            $response = Http::get("{$this->baseUrl}/search", [
                'query'  => $query,
                'limit'  => 10,
                'apikey' => $this->apiKey,
            ]);

            Log::info("FMP Search response for {$query}: " . $response->body());

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error("FMP ERROR (search {$query}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * List tradable stocks (FREE TIER SAFE)
     */
    public function getTradableStocks(): ?array
    {
        $cacheKey = "fmp_tradable_stocks";
        
        return Cache::remember($cacheKey, now()->addDays(30), function () {
            try {
                $response = Http::get("{$this->baseUrl}/stock/list", [
                    'apikey' => $this->apiKey,
                ]);

                Log::info("FMP Tradable Stocks response: " . substr($response->body(), 0, 500) . "...");

                if ($response->successful()) {
                    return $response->json();
                }

                return null;
            } catch (\Exception $e) {
                Log::error("FMP ERROR (tradable stocks): " . $e->getMessage());
                return null;
            }
        });
    }
}
