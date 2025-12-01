<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user has admin role.
     * Returns a JSON error response if the user is not an admin.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                "status" => "failure",
                "payload" => null,
                "message" => "Unauthenticated. Please provide a valid JWT token."
            ], 401);
        }

        // Check if user has admin role
        if (!$user->isAdmin()) {
            return response()->json([
                "status" => "failure",
                "payload" => null,
                "message" => "Access denied. Admin privileges required."
            ], 403);
        }

        return $next($request);
    }
}

