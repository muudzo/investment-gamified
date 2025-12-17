<?php
// routes/api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExternalStockController;

// Public stock endpoints (no auth required)
Route::get('/stocks', [StockController::class, 'index']);
Route::get('/stocks/{symbol}', [StockController::class, 'show']);
Route::get('/stocks/{symbol}/history', [StockController::class, 'history']);

// Authentication routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected endpoints (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Portfolio endpoints
    Route::get('/portfolio', [PortfolioController::class, 'index']);
    Route::get('/portfolio/summary', [PortfolioController::class, 'summary']);
    Route::post('/portfolio/buy', [PortfolioController::class, 'buyStock']);
    Route::post('/portfolio/sell', [PortfolioController::class, 'sellStock']);
    
    // Gamification endpoints
    Route::get('/achievements', [AchievementController::class, 'index']);
    Route::get('/leaderboard', [AchievementController::class, 'leaderboard']);

    // Authenticated user endpoints
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    // External/third-party stock API (AlphaVantage / FMP)
    // Secured with Sanctum and Throttled to prevent quota abuse
    Route::middleware(['throttle:api'])->prefix('external')->group(function () {
        Route::get('/stocks/quote/{symbol}', [ExternalStockController::class, 'quote']);
        Route::get('/stocks/history/{symbol}', [ExternalStockController::class, 'history']);
        Route::get('/stocks/search', [ExternalStockController::class, 'search']);
        Route::get('/stocks/profile/{symbol}', [ExternalStockController::class, 'profile']);
    });
});