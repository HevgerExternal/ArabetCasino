<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\BetsController;
use App\Http\Controllers\GamesController;
use App\Http\Controllers\LvlGameCallbackController;
use App\Http\Controllers\NexusCallbackController;
use App\Http\Controllers\TurbostarsCallbackController;

// Public Routes (Unauthenticated)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/player/login', [PlayerController::class, 'login'])->name('auth.login');
});

// Protected Routes (Authenticated)
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout'); // Accessible to all
        Route::get('/me', [AuthController::class, 'me'])->middleware('token.type:non-player')->name('auth.me');
    });

    // User Management (Restricted to non-player)
    Route::middleware('token.type:non-player')->prefix('users')->group(function () {
        Route::post('/', [UserController::class, 'createUser'])->name('users.create');
        Route::patch('/{userId}/status', [UserController::class, 'changeStatus'])->name('users.changeStatus');
        Route::patch('/{userId}/password', [UserController::class, 'changePassword'])->name('users.changePassword');
        Route::get('/role/{roleId}', [UserController::class, 'getUsersByRole'])->name('users.getByRole');
        Route::post('/search', [UserController::class, 'searchUsers'])->name('users.search');
        Route::get('/hierarchy', [UserController::class, 'getHierarchyTree'])->name('users.hierarchy');
        Route::get('/{userId}', [UserController::class, 'getUserById'])->name('users.getById');
    });

    // Dashboard (Restricted to non-player)
    Route::middleware('token.type:non-player')->prefix('dashboard')->group(function () {
        Route::get('/statistics', [DashboardController::class, 'getStatistics'])->name('dashboard.getStatistics');
    });

    // User Settings (Restricted to non-player)
    Route::middleware('token.type:non-player')->prefix('me')->group(function () {
        Route::patch('/password', function (Request $request, UserController $controller) {
            return $controller->changePassword($request, $request->user()->id);
        })->name('users.changeOwnPassword');
        Route::get('/settings', [UserSettingsController::class, 'getSettings'])->name('settings.get');
        Route::patch('/settings', [UserSettingsController::class, 'updateSettings'])->name('settings.update');
        Route::get('/roles', [UserSettingsController::class, 'getRolesUnderUser'])->name('roles.underUser');
    });

    // Transactions (Restricted to non-player)
    Route::middleware('token.type:non-player')->prefix('transactions')->group(function () {
        Route::post('/', [TransactionController::class, 'createTransaction'])->name('transactions.create');
        Route::get('/', [TransactionController::class, 'getTransactions'])->name('transactions.getAll');
        Route::get('/user/{userId}', [TransactionController::class, 'getUserTransactions'])->name('transactions.getUser');
        Route::get('/user/{userId}/bets', [BetsController::class, 'getPlayerBets'])->name('bets.getPlayerBets');
    });

    // Transactions (Restricted to non-player)
    Route::middleware('token.type:non-player')->prefix('bets')->group(function () {
        Route::get('/user/{userId}', [BetsController::class, 'getPlayerBets'])->name('bets.getPlayerBets');
        Route::get('/{betId}/visualize', [BetsController::class, 'visualizeBet'])->name('bets.visualizeBet');
    });

    Route::middleware('token.type:player')->prefix('player')->group(function () {
        Route::get('/me', [PlayerController::class, 'me'])->name('player.me');
        Route::get('/transactions', [PlayerController::class, 'transactions'])->name('player.transactions');
        Route::get('/bets', [PlayerController::class, 'bets'])->name('player.bets');
    });
    Route::middleware('token.type:player')->prefix('games')->group(function () {
        Route::post(uri: '/open', action: [GamesController::class, 'openGame'])->name('games.open');
    });
});

// Games
Route::prefix('games')->group(function () {
    Route::get('/providers', [GamesController::class, 'getProviders'])->name('games.provider');
    Route::get('/games', [GamesController::class, 'getGames'])->name('games.games');
});

Route::post('/lvlgames/callback', [LvlGameCallbackController::class, 'handleCallback'])->name('lvl.handle');
Route::post('/nexus/gold_api', [NexusCallbackController::class, 'handleCallback'])->name('nexus.handle');

// Sportsbook
Route::prefix('sportsbook/callback')->group(function () {
    Route::post('/user/profile', [TurbostarsCallbackController::class, 'handleCallback'])->name('sportsbook.user.profile');
    Route::post('/user/balance', [TurbostarsCallbackController::class, 'handleCallback'])->name('sportsbook.user.balance');
    Route::match(['post', 'put'], '/payment/bet', [TurbostarsCallbackController::class, 'handleCallback'])->name('sportsbook.payment.bet');
});
