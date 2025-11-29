<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UiModeController;

// Main route - conditionally load UI based on session
Route::get('/', function () {
    // Get UI mode from session, default to 'normal'
    $uiMode = session('ui_mode', 'normal');
    
    // Load appropriate view
    if ($uiMode === 'senior') {
        return view('senior.senior');
    }
    
    return view('normal.welcome');
});

// UI mode switching routes
Route::get('/toggle-ui', [UiModeController::class, 'toggleUiMode']);
Route::get('/set-ui/{mode}', [UiModeController::class, 'setUiMode']);
