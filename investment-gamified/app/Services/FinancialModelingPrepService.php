<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FinancialModelingPrepService implements ExternalStockProvider
{
    private const BASE_URL = 'https://financialmodelingprep.com/api/v3';

    private const MIN_HISTORY_DAYS = 1;

    private const MAX_HISTORY_DAYS = 365;

    private const LOG_BODY_TRUNCATE_LENGTH = 200;

    private ?string $apiKey;

    private CircuitBreaker $circuit;

    private ApiQuotaTracker $quota;

    public function __construct()
    {
        $this->apiKey = config('services.fmp.key');
        $this->circuit = new CircuitBreaker('fmp');
        $this->quota = new ApiQuotaTracker;
    }

    public function getQuote(string $symbol): ?array
    {
        $cacheKey = "fmp_quote_{$symbol}";

        // Try stale cache first as fallback
        $stale = Cache::get($cacheKey);

        return $this->circuit->call(function () use ($symbol, $cacheKey) {
            if (! $this->quota->hasQuota('fmp')) {
                Log::warning('FMP quota exhausted, returning stale cache if available');

                return Cache::get($cacheKey);
            }

            $this->quota->recordRequest('fmp');

            return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($symbol) {
                try {
                    $response = Http::timeout(10)->get(self::BASE_URL.'/quote/'.rawurlencode($symbol), [
                        'apikey' => $this->apiKey,
                    ]);
                } catch (\Throwable $e) {
                    // Re-thrown as a sanitized exception: the underlying
                    // HTTP client exception can embed the full request URL
                    // (including the apikey query param), and that message
                    // would otherwise bubble up into CircuitBreaker's logger.
                    Log::error("FMP request failed for quote {$symbol}: ".$this->sanitizeErrorMessage($e));
                    throw new \RuntimeException('FMP request failed');
                }

                $this->logResponse('Quote', $symbol, $response->body());

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['Error Message'])) {
                        Log::error("FMP API Error for {$symbol}: ".$data['Error Message']);
                        throw new \Exception('FMP API error');
                    }

                    if (! empty($data) && isset($data[0])) {
                        $quote = $data[0];

                        return [
                            'symbol' => $quote['symbol'] ?? null,
                            'price' => $quote['price'] ?? null,
                            'volume' => $quote['volume'] ?? null,
                            'change' => $quote['change'] ?? null,
                            'changesPercentage' => $quote['changesPercentage'] ?? null,
                        ];
                    }
                }

                throw new \Exception('FMP unexpected response');
            });
        }, function () use ($stale) {
            return $stale ?? null;
        });
    }

    public function getCompanyProfile(string $symbol): ?array
    {
        $cacheKey = "fmp_profile_{$symbol}";

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($symbol): ?array {
            try {
                $response = Http::timeout(10)->get(self::BASE_URL.'/profile/'.rawurlencode($symbol), [
                    'apikey' => $this->apiKey,
                ]);

                $this->logResponse('Profile', $symbol, $response->body());

                if ($response->successful()) {
                    $data = $response->json();

                    if ($this->hasApiError($data, "profile {$symbol}")) {
                        return null;
                    }

                    if (! empty($data) && isset($data[0])) {
                        return $data[0];
                    }
                }

                return null;
            } catch (\Exception $e) {
                Log::error("FMP ERROR (profile {$symbol}): ".$this->sanitizeErrorMessage($e));

                return null;
            }
        });
    }

    public function getHistoricalPrices(string $symbol, int $days = 30): ?array
    {
        $days = max(self::MIN_HISTORY_DAYS, min(self::MAX_HISTORY_DAYS, $days));
        $cacheKey = "fmp_history_{$symbol}_{$days}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($symbol, $days): ?array {
            try {
                $from = now()->subDays($days)->format('Y-m-d');
                $to = now()->format('Y-m-d');

                $response = Http::timeout(10)->get(self::BASE_URL.'/historical-price-full/'.rawurlencode($symbol), [
                    'from' => $from,
                    'to' => $to,
                    'apikey' => $this->apiKey,
                ]);

                $this->logResponse('History', $symbol, $response->body());

                if ($response->successful()) {
                    $data = $response->json();

                    if ($this->hasApiError($data, "history {$symbol}")) {
                        return null;
                    }

                    if (isset($data['historical'])) {
                        return $data['historical'];
                    }
                }

                return null;
            } catch (\Exception $e) {
                Log::error("FMP ERROR (history {$symbol}): ".$this->sanitizeErrorMessage($e));

                return null;
            }
        });
    }

    public function searchStocks(string $query): ?array
    {
        $cacheKey = 'fmp_search_'.md5($query);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($query): ?array {
            try {
                $response = Http::timeout(10)->get(self::BASE_URL.'/search', [
                    'query' => $query,
                    'limit' => 10,
                    'apikey' => $this->apiKey,
                ]);

                $this->logResponse('Search', $query, $response->body());

                if ($response->successful()) {
                    $data = $response->json();

                    if ($this->hasApiError($data, "search {$query}")) {
                        return null;
                    }

                    return $data;
                }

                return null;
            } catch (\Exception $e) {
                Log::error("FMP ERROR (search {$query}): ".$this->sanitizeErrorMessage($e));

                return null;
            }
        });
    }

    public function getTradableStocks(): ?array
    {
        $cacheKey = 'fmp_tradable_stocks';

        return Cache::remember($cacheKey, now()->addDays(30), function (): ?array {
            try {
                $response = Http::get(self::BASE_URL.'/stock/list', [
                    'apikey' => $this->apiKey,
                ]);

                $this->logResponse('Tradable Stocks', '(all)', $response->body());

                if ($response->successful()) {
                    return $response->json();
                }

                return null;
            } catch (\Exception $e) {
                Log::error('FMP ERROR (tradable stocks): '.$this->sanitizeErrorMessage($e));

                return null;
            }
        });
    }

    private function hasApiError(array $data, string $context): bool
    {
        if (! isset($data['Error Message'])) {
            return false;
        }

        Log::error('FMP API Error for '.$context.': '.$data['Error Message']);

        return true;
    }

    /**
     * Debug-level, truncated response logging. Never logs at info level and
     * never includes the request (which carries the apikey) - only the
     * response body, truncated, is recorded.
     */
    private function logResponse(string $label, string $context, string $body): void
    {
        Log::debug("FMP {$label} response for {$context}: ".substr($body, 0, self::LOG_BODY_TRUNCATE_LENGTH));
    }

    /**
     * HTTP client exceptions (timeouts, DNS failures, etc.) can embed the
     * full request URI - including the `apikey` query parameter - in their
     * message. Strip it before the message is ever logged.
     */
    private function sanitizeErrorMessage(\Throwable $e): string
    {
        $message = preg_replace('/apikey=[^&\s]+/i', 'apikey=***', $e->getMessage());

        return $message ?? 'request failed';
    }
}
