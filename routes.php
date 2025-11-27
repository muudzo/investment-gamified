<?php

// routes/api.php
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\AchievementController;
//protetcted endpoints (require authentication )
Route::middleware('auth:sanctum')->group(function () {
    // Portfolio endpoints
    Route::get('/portfolio', [PortfolioController::class, 'index']);
    Route::get('/portfolio/summary', [PortfolioController::class, 'summary']);
    Route::post('/portfolio/buy', [PortfolioController::class, 'buyStock']);
    Route::post('/portfolio/sell', [PortfolioController::class, 'sellStock']);
    
    // Stock market endpoints
    Route::get('/stocks', [StockController::class, 'index']);
    Route::get('/stocks/{symbol}', [StockController::class, 'show']);
    Route::get('/stocks/{symbol}/history', [StockController::class, 'history']);
    
    // Gamification endpoints
    Route::get('/achievements', [AchievementController::class, 'index']);
    Route::get('/leaderboard', [AchievementController::class, 'leaderboard']);
});

// Portfolio controller moved to app/Http/Controllers/Api/PortfolioController.php

// StockController moved to app/Http/Controllers/Api/StockController.php

// AchievementController moved to app/Http/Controllers/Api/AchievementController.php