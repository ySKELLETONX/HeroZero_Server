<?php

use App\Http\Controllers\BetaApiController;
use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

// API do jogo (o cliente html5_257 POSTa exatamente em /request.php).
Route::match(['post', 'options'], '/request.php', [GameController::class, 'request']);

// Boot do cliente desktop (Steam/NW.js).
Route::get('/steam.php', [GameController::class, 'steam']);

// API beta de conta (registrar/login fora do protocolo do jogo).
Route::post('/beta-api', [BetaApiController::class, 'handle']);

// Logs do cliente (dev).
Route::post('/clientlog', [GameController::class, 'clientlog']);

// Raiz -> pagina de embarque do cliente (public/index.html).
Route::get('/', fn () => response()->file(public_path('index.html')));
