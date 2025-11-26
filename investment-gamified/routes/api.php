<?php
// routes/api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\AchievementController;

// Public stock endpoints (no auth required)
Route::get('/stocks', [StockController::class, 'index']);
Route::get('/stocks/{symbol}', [StockController::class, 'show']);
Route::get('/stocks/{symbol}/history', [StockController::class, 'history']);

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
});