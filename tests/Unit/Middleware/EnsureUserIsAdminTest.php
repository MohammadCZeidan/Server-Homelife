<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EnsureUserIsAdminTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed user roles for testing
        UserRole::firstOrCreate(['role' => 'admin']);
        UserRole::firstOrCreate(['role' => 'member']);
    }
    public function test_admin_user_passes_middleware(): void
    {
        // Create admin role
        $adminRole = UserRole::firstOrCreate(['role' => 'admin']);
        
        // Create a user with admin role
        $user = User::factory()->create();
        $user->user_role_id = $adminRole->id;
        $user->save();
        $user->load('role');

        // Create a request
        $request = Request::create('/api/v0.1/admin/users', 'GET');
        
        // Authenticate the user (real authentication)
        Auth::setUser($user);

        // Create middleware instance
        $middleware = new EnsureUserIsAdmin();
        
        // Create a closure that represents the next middleware/controller
        $next = function ($req) {
            return response()->json(['status' => 'success']);
        };

        // Execute middleware
        $response = $middleware->handle($request, $next);

        // Assert middleware passes (returns response from next)
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_member_user_is_rejected(): void
    {
        // Create member role
        $memberRole = UserRole::firstOrCreate(['role' => 'member']);
        
        // Create a user with member role
        $user = User::factory()->create();
        $user->user_role_id = $memberRole->id;
        $user->save();
        $user->load('role');

        // Create a request
        $request = Request::create('/api/v0.1/admin/users', 'GET');
        
        // Authenticate the user (real authentication)
        Auth::setUser($user);

        // Create middleware instance
        $middleware = new EnsureUserIsAdmin();
        
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
        $this->assertStringContainsString('Admin privileges', $responseData['message']);
    }

    // Note: Unauthenticated user test is covered in feature tests
    // Unit testing unauthenticated state is complex with JWT due to token parsing
    // The feature tests properly handle JWT authentication scenarios

    public function test_user_without_role_is_rejected(): void
    {
        // Create a user without role
        $user = User::factory()->create(['user_role_id' => null]);

        // Create a request
        $request = Request::create('/api/v0.1/admin/users', 'GET');
        
        // Authenticate the user (real authentication)
        Auth::setUser($user);

        // Create middleware instance
        $middleware = new EnsureUserIsAdmin();
        
        // Create a closure that represents the next middleware/controller
        $next = function ($req) {
            return response()->json(['status' => 'success']);
        };

        // Execute middleware
        $response = $middleware->handle($request, $next);

        // Assert middleware rejects (returns 403)
        $this->assertEquals(403, $response->getStatusCode());
    }
}

