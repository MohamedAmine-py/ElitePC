<?php

use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategorieController;
use App\Http\Controllers\API\CommandeController;
use App\Http\Controllers\API\ProduitController;
use App\Http\Controllers\API\SupportChatController;
use Illuminate\Support\Facades\Route;

/*
 * API ROUTES — all routes here are prefixed with /api automatically by Laravel.
 *
 * ROUTE GROUPS EXPLAINED:
 * - No middleware  → anyone can access (public)
 * - auth:sanctum   → must be logged in (has valid token)
 * - auth:sanctum + admin → must be logged in AND be an admin
 */

// ── PUBLIC ROUTES ──────────────────────────────────────────────
// No authentication required

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Products & categories are public (anyone can browse)
Route::get('/products', [ProduitController::class, 'index']);
Route::get('/products/{id}', [ProduitController::class, 'show']);
Route::get('/categories', [CategorieController::class, 'index']);

// Support Chat agent route — rate-limited to 30 requests/minute/IP
Route::post('/support/chat', [SupportChatController::class, 'handleChat'])
    ->middleware('throttle:ai-chat');

// ── AUTHENTICATED ROUTES ───────────────────────────────────────
// Must send Authorization: Bearer {token} header

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    // Orders — clients can place and view their own orders
    Route::get('/orders', [CommandeController::class, 'index']);
    Route::post('/orders', [CommandeController::class, 'store']);
    Route::get('/orders/{id}', [CommandeController::class, 'show']);
    Route::get('/orders/{id}/invoice', [CommandeController::class, 'downloadInvoice']);

    // ── ADMIN ONLY ROUTES ──────────────────────────────────────
    // Must be logged in AND have role = 'admin'

    Route::middleware('admin')->group(function () {

        // Product management
        Route::post('/products', [ProduitController::class, 'store']);
        Route::put('/products/{id}', [ProduitController::class, 'update']);
        Route::delete('/products/{id}', [ProduitController::class, 'destroy']);

        // Category management
        Route::post('/categories', [CategorieController::class, 'store']);
        Route::put('/categories/{id}', [CategorieController::class, 'update']);
        Route::delete('/categories/{id}', [CategorieController::class, 'destroy']);

        // Order status management
        Route::patch('/orders/{id}/status', [CommandeController::class, 'updateStatus']);
        Route::delete('/orders/{id}', [CommandeController::class, 'destroy']);

        // Admin dashboard
        Route::get('/admin/stats', [AdminController::class, 'stats']);
        Route::get('/admin/users', [AdminController::class, 'users']);
        Route::post('/admin/users', [AdminController::class, 'storeUser']);
        Route::put('/admin/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/admin/users/{id}', [AdminController::class, 'destroyUser']);
    });
});
