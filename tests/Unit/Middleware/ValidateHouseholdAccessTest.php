<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\ValidateHouseholdAccess;
use App\Models\User;
use App\Models\Household;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ValidateHouseholdAccessTest extends TestCase
{
    use RefreshDatabase;
    public function test_user_accessing_own_household_passes(): void
    {
        // Create a household and user
        $household = Household::factory()->create();
        $user = User::factory()->create(['household_id' => $household->id]);

        // Create a request with household ID in route
        $request = Request::create("/api/v0.1/household/{$household->id}", 'GET');
        $request->setRouteResolver(function () use ($household) {
            $route = new \Illuminate\Routing\Route(['GET'], '/api/v0.1/household/{id}', []);
            $route->bind($request = new Request());
            $route->setParameter('id', $household->id);
            return $route;
        });
        
        // Authenticate the user (real authentication)
        Auth::setUser($user);

        // Create middleware instance
        $middleware = new ValidateHouseholdAccess();
        
        // Create a closure that represents the next middleware/controller
        $next = function ($req) {
            return response()->json(['status' => 'success']);
        };

        // Execute middleware
        $response = $middleware->handle($request, $next);

        // Assert middleware passes
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_user_accessing_different_household_is_rejected(): void
    {
        // Create two households
        $userHousehold = Household::factory()->create();
        $otherHousehold = Household::factory()->create();
        
        // Create a user belonging to first household
        $user = User::factory()->create(['household_id' => $userHousehold->id]);

        // Create a request trying to access other household
        $request = Request::create("/api/v0.1/household/{$otherHousehold->id}", 'GET');
        $request->setRouteResolver(function () use ($otherHousehold) {
            $route = new \Illuminate\Routing\Route(['GET'], '/api/v0.1/household/{id}', []);
            $route->bind($request = new Request());
            $route->setParameter('id', $otherHousehold->id);
            return $route;
        });
        
        // Authenticate the user (real authentication)
        Auth::setUser($user);

        // Create middleware instance
        $middleware = new ValidateHouseholdAccess();
        
        // Create a closure that represents the next middleware/controller
        $next = function ($req) {
            return response()->json(['status' => 'success']);
        };

        // Execute middleware
        $response = $middleware->handle($request, $next);

        // Assert middleware rejects (returns 403)
        $this->assertEquals(403, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('failure', $responseData['status']);
        $this->assertStringContainsString('do not have access', $responseData['message']);
    }

    public function test_user_without_household_is_rejected(): void
    {
        // Create a user without household
        $user = User::factory()->create(['household_id' => null]);

        // Create a request
        $request = Request::create('/api/v0.1/household/1', 'GET');
        
        // Authenticate the user (real authentication)
        Auth::setUser($user);

        // Create middleware instance
        $middleware = new ValidateHouseholdAccess();
        
        // Create a closure that represents the next middleware/controller
        $next = function ($req) {
            return response()->json(['status' => 'success']);
        };

        // Execute middleware
        $response = $middleware->handle($request, $next);

        // Assert middleware rejects (returns 400)
        $this->assertEquals(400, $response->getStatusCode());
    }
}

