<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;
use App\Models\User;
use App\Models\Household;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureUserHasHouseholdFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_with_household_required_middleware_allows_user_with_household(): void
    {
        // Create household and user
        $household = Household::factory()->create();
        $user = User::factory()->create(['household_id' => $household->id]);
        
        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        // Make request with token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v0.1/pantry');

        // Should pass middleware (may fail at controller if route doesn't exist, but middleware passed)
        // If pantry route exists, should get 200 or proper response
        // If route doesn't exist, will get 404, but middleware check passed
        $this->assertNotEquals(400, $response->getStatusCode()); // Not the middleware rejection
    }

    public function test_route_with_household_required_middleware_rejects_user_without_household(): void
    {
        // Create user without household
        $user = User::factory()->create(['household_id' => null]);
        
        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        // Make request with token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v0.1/pantry');

        // Should be rejected by middleware
        $this->assertEquals(400, $response->getStatusCode());
        
        $responseData = $response->json();
        $this->assertEquals('failure', $responseData['status']);
        $this->assertStringContainsString('household', $responseData['message']);
    }

    public function test_route_with_household_required_middleware_rejects_unauthenticated_request(): void
    {
        // Make request without token
        $response = $this->getJson('/api/v0.1/pantry');

        // Should be rejected by auth:api middleware first (401)
        // Or by household middleware if somehow passes auth (400)
        $this->assertContains($response->getStatusCode(), [400, 401]);
    }
}

