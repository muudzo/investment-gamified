<?php

declare(strict_types=1);

namespace App\Services;

interface ExternalStockProvider
{
    public function getQuote(string $symbol): ?array;

    public function getHistoricalPrices(string $symbol, int $days = 30): ?array;

    public function searchStocks(string $query): ?array;

    public function getCompanyProfile(string $symbol): ?array;
}
