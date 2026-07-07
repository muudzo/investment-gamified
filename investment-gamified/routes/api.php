<?php

// routes/api.php
use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExternalStockController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\StockController;
use Illuminate\Support\Facades\Route;

// Public stock endpoints (no auth required)
Route::get('/stocks', [StockController::class, 'index']);
Route::get('/stocks/{symbol}', [StockController::class, 'show']);
Route::get('/stocks/{symbol}/history', [StockController::class, 'history']);

// Authentication routes (rate limited to blunt credential stuffing / mass signup).
// The 'login' and 'register' limiters are defined in AppServiceProvider.
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Protected endpoints (require authentication).
// The 'api' limiter (120/min per user) is a general safety net.
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Portfolio endpoints
    Route::get('/portfolio', [PortfolioController::class, 'index']);
    Route::get('/portfolio/summary', [PortfolioController::class, 'summary']);

    // Trades are idempotent: clients send an Idempotency-Key header so a retried
    // POST cannot double-execute. The 'idempotent' alias is registered in bootstrap/app.php
    // and runs after auth:sanctum because idempotency is scoped per authenticated user.
    Route::post('/portfolio/buy', [PortfolioController::class, 'buyStock'])->middleware('idempotent');
    Route::post('/portfolio/sell', [PortfolioController::class, 'sellStock'])->middleware('idempotent');

    // Gamification endpoints
    Route::get('/achievements', [AchievementController::class, 'index']);
    Route::get('/leaderboard', [AchievementController::class, 'leaderboard']);

    // Authenticated user endpoints
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // External/third-party stock API (AlphaVantage / FMP).
    // Throttled tighter than the general API because upstream free-tier quotas are small.
    // The {symbol} constraint intentionally allows any characters so malformed/traversal
    // input is dispatched to the controller's FormRequest and rejected with a 422 (rather
    // than a bare 404 from the router), keeping validation behaviour consistent.
    Route::middleware(['throttle:10,1'])->prefix('external')->group(function () {
        Route::get('/stocks/quote/{symbol}', [ExternalStockController::class, 'quote'])->where('symbol', '.*');
        Route::get('/stocks/history/{symbol}', [ExternalStockController::class, 'history'])->where('symbol', '.*');
        Route::get('/stocks/search', [ExternalStockController::class, 'search']);
        Route::get('/stocks/profile/{symbol}', [ExternalStockController::class, 'profile'])->where('symbol', '.*');
    });
});
