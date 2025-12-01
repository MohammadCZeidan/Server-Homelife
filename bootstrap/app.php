<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Remove all session-related middleware - JWT only, no sessions
        $middleware->web(remove: [
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ]);
        
        // Add minimal session initialization to prevent errors
        $middleware->web(prepend: [
            \App\Http\Middleware\InitializeSession::class,
        ]);
        
        // API routes never use sessions
        $middleware->api(remove: [
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ]);
        
        // Configure CORS for API routes - allow React frontend
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        // Register custom middleware aliases
        $middleware->alias([
            'household.required' => \App\Http\Middleware\EnsureUserHasHousehold::class,
            'household.validate' => \App\Http\Middleware\ValidateHouseholdAccess::class,
            'admin.only' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
        
        // Configure authentication to return JSON for API routes instead of redirecting
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null; // Don't redirect API routes
            }
            return '/login'; // Only redirect web routes
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle validation exceptions for API routes - return custom JSON format
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'failure',
                    'payload' => null,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
        });
        
        // Handle authentication exceptions for API routes - return JSON instead of redirecting
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'failure',
                    'payload' => null,
                    'message' => 'Unauthenticated. Please provide a valid JWT token in the Authorization header.'
                ], 401);
            }
        });
        
        // Handle route not found for login route
        $exceptions->render(function (\Symfony\Component\Routing\Exception\RouteNotFoundException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson() || $request->wantsJson()) {
                // If it's trying to redirect to login, return 401 instead
                if (str_contains($e->getMessage(), 'login')) {
                    return response()->json([
                        'status' => 'failure',
                        'payload' => null,
                        'message' => 'Unauthenticated. Please provide a valid JWT token in the Authorization header.'
                    ], 401);
                }
            }
        });
    })->create();
