<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\UserController;

// Versioned API routes
Route::prefix('v1')->group(function () {
    // Users API
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::get('/health', [HealthController::class, 'health']);
    Route::get('/metrics', [MetricsController::class, 'metrics']);

    Route::get('/widgets/{id}', function (string $id) {
        return response()->json(['id' => $id, 'name' => 'example']);
    });

    Route::get('/secure/ping', function () {
        return response()->json(['pong' => true]);
    })->middleware('auth.jwt');



    // Validation error test endpoint
    Route::post('/test/validation', function (Request $request) {
        $validated = $request->validate(
            ['email' => ['required', 'email']],
            [
                'email.required' => 'Email is required',
                'email.email' => 'Must be a valid email address',
            ]
        );
        return response()->json([
            'success' => true,
            'data' => ['email' => $validated['email']],
            'message' => 'Valid email'
        ]);
    });

    // Secure duplicates for all endpoints (Auth: Bearer JWT)
    // Demonstrates protecting the same resources under /v1/secure/*
    Route::prefix('secure')->group(function () {
        // Users (read)
        Route::get('/users', [UserController::class, 'index'])->middleware('auth.jwt');
        Route::get('/users/{id}', [UserController::class, 'show'])->middleware('auth.jwt');

        // Users (write)
        Route::post('/users', [UserController::class, 'store'])->middleware('auth.jwt');
        Route::put('/users/{id}', [UserController::class, 'update'])->middleware('auth.jwt');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('auth.jwt');

        // Health and Metrics (read)
        Route::get('/health', [HealthController::class, 'health'])->middleware('auth.jwt');
        Route::get('/metrics', [MetricsController::class, 'metrics'])->middleware('auth.jwt');

        // Widgets example (read)
        Route::get('/widgets/{id}', function (string $id) {
            return response()->json(['id' => $id, 'name' => 'example']);
        })->middleware('auth.jwt');

        // Validation example (write)
        Route::post('/test/validation', function (Request $request) {
            $validated = $request->validate(
                ['email' => ['required', 'email']],
                [
                    'email.required' => 'Email is required',
                    'email.email' => 'Must be a valid email address',
                ]
            );
            return response()->json([
                'success' => true,
                'data' => ['email' => $validated['email']],
                'message' => 'Valid email'
            ]);
        })->middleware('auth.jwt');
    });
});

// Redirect /docs to Swagger UI
Route::get('/docs', function () {
    return redirect('/api/documentation');
});
