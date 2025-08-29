<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MetricsController;

// Versioned API routes
Route::prefix('v1')->group(function () {
    Route::get('/health', [HealthController::class, 'health']);
    Route::get('/metrics', [MetricsController::class, 'metrics']);

    Route::get('/widgets/{id}', function (string $id) {
        return response()->json(['id' => $id, 'name' => 'example']);
    });

    Route::get('/secure/ping', function () {
        return response()->json(['pong' => true]);
    })->middleware('auth.jwt:example.read');

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
});

// Redirect /docs to Swagger UI
Route::get('/docs', function () {
    return redirect('/api/documentation');
});
