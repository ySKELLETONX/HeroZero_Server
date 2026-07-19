<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\GuildController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard']);

    // Contas
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users/{id}', [UserController::class, 'update']);
    Route::post('/users/{id}/delete', [UserController::class, 'destroy']);

    // Personagem (dados, inventario, missoes, eventos)
    Route::get('/chars/{id}', [CharacterController::class, 'show']);
    Route::post('/chars/{id}', [CharacterController::class, 'update']);
    Route::post('/chars/{id}/items', [CharacterController::class, 'giveItem']);
    Route::post('/chars/{id}/items/{itemId}/delete', [CharacterController::class, 'deleteItem']);
    Route::post('/chars/{id}/quests', [CharacterController::class, 'saveQuest']);
    Route::post('/chars/{id}/quests/{questId}/delete', [CharacterController::class, 'deleteQuest']);
    Route::post('/chars/{id}/events', [CharacterController::class, 'saveEvent']);
    Route::post('/chars/{id}/events/{eventId}/delete', [CharacterController::class, 'deleteEvent']);

    // Guildas
    Route::get('/guilds', [GuildController::class, 'index']);
    Route::get('/guilds/{id}', [GuildController::class, 'show']);
    Route::post('/guilds/{id}', [GuildController::class, 'update']);

    // Mensagem para o servidor (chat das guildas)
    Route::get('/broadcast', [AdminController::class, 'showBroadcast']);
    Route::post('/broadcast', [AdminController::class, 'sendBroadcast']);
});
