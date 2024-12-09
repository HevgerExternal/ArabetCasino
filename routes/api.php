<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\TransactionController;

// Auth
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // User management
    Route::post('/users', [UserController::class, 'createUser']);
    Route::patch('/users/{userId}/status', [UserController::class, 'changeStatus']);
    Route::patch('/users/{userId}/password', [UserController::class, 'changePassword']);
    Route::get('/users/role/{roleId}', [UserController::class, 'getUsersByRole']);
    Route::get('/users/search', [UserController::class, 'searchUsers']);
    Route::get('/users/hierarchy', [UserController::class, 'getHierarchyTree']);

    // User settings
    Route::get('/me/settings', [UserSettingsController::class, 'getSettings']);
    Route::patch('/me/settings', [UserSettingsController::class, 'updateSettings']);
    Route::get('/me/roles', [UserSettingsController::class, 'getRolesUnderUser']);

    // Transaction
    Route::post('/transactions', [TransactionController::class, 'createTransaction']);
    Route::get('/transactions', [TransactionController::class, 'getTransactions']);
    Route::get('/transactions/user/{userId}', [TransactionController::class, 'getUserTransactions']);
});
