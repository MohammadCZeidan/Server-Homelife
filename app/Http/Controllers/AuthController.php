<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AuthService;

class AuthController extends Controller
{
    private $authService;

    function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function displayError()
    {
        return $this->responseJSON(null, "failure", 401);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = $this->authService->login($request->email, $request->password);
        
        if (!$user) {
            return $this->responseJSON(null, "failure", 401);
        }

        return $this->responseJSON($user);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = $this->authService->register($request->name, $request->email, $request->password);
        return $this->responseJSON($user);
    }

    public function logout()
    {
        $this->authService->logout();
        return $this->responseJSON(null, "success");
    }

    public function refresh()
    {
        $user = $this->authService->refresh();
        return $this->responseJSON($user);
    }

    public function me()
    {
        $user = $this->authService->me();
        return $this->responseJSON($user);
    }

    public function getAllUsers()
    {
        // Get all users with their roles and household information
        $users = \App\Models\User::with(['role', 'household'])
            ->select('id', 'name', 'email', 'user_role_id', 'household_id', 'created_at')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'role' => $user->role->role,
                    ] : null,
                    'household' => $user->household ? [
                        'id' => $user->household->id,
                        'name' => $user->household->name,
                    ] : null,
                    'created_at' => $user->created_at,
                ];
            });

        return $this->responseJSON($users);
    }

    public function updateProfile(Request $request)
    {
        $user = $this->authService->me();
        
        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)
            ],
        ]);

        $user = $this->authService->updateProfile($request->all());
        return $this->responseJSON($user);
    }
}

