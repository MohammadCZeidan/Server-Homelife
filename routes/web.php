<?php

use Illuminate\Support\Facades\Route;

// Test route to verify server is working
Route::get('/test', function () {
    return response()->json(['message' => 'Server is working!', 'routes' => 'Check /api/v0.1/auth/register']);
});

// Root route - return JSON response (no redirect to avoid session issues)
Route::get('/', function () {
    return response()->json([
        'message' => 'Pantry API Server',
        'version' => 'v0.1',
        'endpoints' => [
            'register' => '/api/v0.1/auth/register',
            'login' => '/api/v0.1/auth/login',
            'test' => '/test'
        ]
    ]);
});
