<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Session\Store;
use Illuminate\Session\SessionManager;

class InitializeSession
{
    /**
     * Handle an incoming request.
     *
     * Initialize a minimal session store (array driver) to prevent
     * "Session store not set on request" errors.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Force session driver to array
        config(['session.driver' => 'array']);
        
        // Initialize session store if not already set
        if (!$request->hasSession()) {
            $sessionManager = app('session');
            $sessionStore = $sessionManager->driver('array');
            $request->setLaravelSession($sessionStore);
        }
        
        return $next($request);
    }
}

