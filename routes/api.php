<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DashboardController;

// Public Routes (Unauthenticated)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
});

// Protected Routes (Authenticated)
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
    });

    // User Management
    Route::prefix('users')->group(function () {
        Route::post('/', [UserController::class, 'createUser'])->name('users.create');
        Route::patch('/{userId}/status', [UserController::class, 'changeStatus'])->name('users.changeStatus');
        Route::patch('/{userId}/password', [UserController::class, 'changePassword'])->name('users.changePassword');
        Route::get('/role/{roleId}', [UserController::class, 'getUsersByRole'])->name('users.getByRole');
        Route::post('/search', [UserController::class, 'searchUsers'])->name('users.search');
        Route::get('/hierarchy', [UserController::class, 'getHierarchyTree'])->name('users.hierarchy');
        Route::get('/{userId}', [UserController::class, 'getUserById'])->name('users.getById');
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/statistics', [DashboardController::class, 'getStatistics'])->name('dashboard.getStatistics');
    });

    // User Settings
    Route::prefix('me')->group(function () {
        Route::patch('/password', function (Request $request, UserController $controller) {
            return $controller->changePassword($request, $request->user()->id);
        })->name('users.changeOwnPassword');
        Route::get('/settings', [UserSettingsController::class, 'getSettings'])->name('settings.get');
        Route::patch('/settings', [UserSettingsController::class, 'updateSettings'])->name('settings.update');
        Route::get('/roles', [UserSettingsController::class, 'getRolesUnderUser'])->name('roles.underUser');
    });

    // Transactions
    Route::prefix('transactions')->group(function () {
        Route::post('/', [TransactionController::class, 'createTransaction'])->name('transactions.create');
        Route::get('/', [TransactionController::class, 'getTransactions'])->name('transactions.getAll');
        Route::get('/user/{userId}', [TransactionController::class, 'getUserTransactions'])->name('transactions.getUser');
    });
});
