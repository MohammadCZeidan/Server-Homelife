<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureUserIsAdminFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed user roles
        UserRole::firstOrCreate(['role' => 'admin']);
        UserRole::firstOrCreate(['role' => 'member']);
        
        // Register a test route with admin middleware for testing
        Route::middleware(['auth:api', 'admin.only'])
            ->get('/test-admin-route', function () {
                return response()->json(['status' => 'success']);
            });
    }

    public function test_admin_route_allows_admin_user(): void
    {
        // Create admin user
        $adminRole = UserRole::where('role', 'admin')->first();
        $user = User::factory()->create(['user_role_id' => $adminRole->id]);
        
        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        // Make request to test admin route
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/test-admin-route');

        // Should pass middleware
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = $response->json();
        $this->assertEquals('success', $responseData['status']);
    }

    public function test_admin_route_rejects_member_user(): void
    {
        // Create member user
        $memberRole = UserRole::where('role', 'member')->first();
        $user = User::factory()->create(['user_role_id' => $memberRole->id]);
        
        // Generate JWT token
        $token = JWTAuth::fromUser($user);


        // Make request to test admin route
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/test-admin-route');

        // Should be rejected by middleware
        $this->assertEquals(403, $response->getStatusCode());
        
        $responseData = $response->json();
        $this->assertEquals('failure', $responseData['status']);
        $this->assertStringContainsString('Admin privileges', $responseData['message']);
    }

    public function test_admin_route_rejects_user_without_role(): void
    {
        // Create user without role
        $user = User::factory()->create(['user_role_id' => null]);
        
        // Generate JWT token
        $token = JWTAuth::fromUser($user);


        // Make request to test admin route
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/test-admin-route');

        // Should be rejected by middleware
        $this->assertEquals(403, $response->getStatusCode());
    }
}

