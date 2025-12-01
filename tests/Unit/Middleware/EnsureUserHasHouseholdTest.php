<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\EnsureUserHasHousehold;
use App\Models\User;
use App\Models\Household;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EnsureUserHasHouseholdTest extends TestCase
{
    use RefreshDatabase;
    public function test_user_with_household_passes_middleware(): void
    {
        // Create a household and user
        $household = Household::factory()->create();
        $user = User::factory()->create(['household_id' => $household->id]);

        // Create a request
        $request = Request::create('/api/v0.1/pantry', 'GET');
        
        // Authenticate the user (real authentication, not mock)
        Auth::setUser($user);

        // Create middleware instance
        $middleware = new EnsureUserHasHousehold();
        
        // Create a closure that represents the next middleware/controller
        $next = function ($req) {
            return response()->json(['status' => 'success']);
        };

        // Execute middleware
        $response = $middleware->handle($request, $next);

        // Assert middleware passes (returns response from next)
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function test_user_without_household_is_rejected(): void
    {
        // Create a user without household
        $user = User::factory()->create(['household_id' => null]);

        // Create a request
        $request = Request::create('/api/v0.1/pantry', 'GET');
        
        // Authenticate the user (real authentication)
        Auth::setUser($user);

        // Create middleware instance
        $middleware = new EnsureUserHasHousehold();
        
        // Create a closure that represents the next middleware/controller
        $next = function ($req) {
            return response()->json(['status' => 'success']);
        };

        // Execute middleware
        $response = $middleware->handle($request, $next);

        // Assert middleware rejects (returns 400)
        $this->assertEquals(400, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('failure', $responseData['status']);
        $this->assertStringContainsString('household', $responseData['message']);
    }

    // Note: Unauthenticated user test is covered in feature tests
    // Unit testing unauthenticated state is complex with JWT due to token parsing
    // See: EnsureUserHasHouseholdFeatureTest::test_route_with_household_required_middleware_rejects_unauthenticated_request
}

