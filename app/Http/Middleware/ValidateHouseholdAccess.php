<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ValidateHouseholdAccess
{
    /**
     * Handle an incoming request.
     *
     * Validates that the user can only access resources belonging to their household.
     * This prevents users from accessing other households' data by manipulating IDs.
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
                "message" => "You must create or join a household first."
            ], 400);
        }

        // Get household_id from route parameters or request body
        $householdId = $request->route('householdId') 
            ?? $request->input('household_id')
            ?? $request->route('id'); // Some routes use 'id' for household resources

        // If household_id is provided, validate it matches user's household
        if ($householdId && $householdId != $user->household_id) {
            return response()->json([
                "status" => "failure",
                "payload" => null,
                "message" => "You do not have access to this household's resources."
            ], 403);
        }

        return $next($request);
    }
}

