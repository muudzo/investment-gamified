<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
| Two distinct front-end experiences that share one account/backend:
|   /     -> Kids game (playful learning surface)
|   /pro  -> Pro trading (the real, data-dense trading cockpit)
|
| They are separate pages by design — not a session-driven toggle inside the
| kids game. Switching happens through a deliberate, animated experience
| switcher rendered in each page's header.
*/

Route::view('/', 'normal.welcome')->name('play');
Route::view('/pro', 'senior.senior')->name('pro');
