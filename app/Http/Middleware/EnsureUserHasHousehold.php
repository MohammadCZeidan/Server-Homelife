<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasHousehold
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user belongs to a household.
     * Returns a JSON error response if the user doesn't have a household.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        if (!$user || !$user->household_id) {
            return response()->json([
                "status" => "failure",
                "payload" => null,
                "message" => "You must create or join a household first. Use POST /api/v0.1/household to create one."
            ], 400);
        }

        return $next($request);
    }
}

