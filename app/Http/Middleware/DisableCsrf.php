<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisableCsrf
{
    /**
     * Handle an incoming request.
     * 
     * This middleware completely bypasses CSRF verification since we use JWT tokens.
     * CSRF protection is not needed for stateless JWT authentication.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Simply pass through - no CSRF validation needed for JWT
        return $next($request);
    }
}

