<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\AuthService;
use App\Models\User;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function displayError(): JsonResponse
    {
        return $this->responseJSON(null, "failure", 401);
    }

    public function login(Request $request): JsonResponse
    {
        $this->validateRequest($request, 'login');

        $user = $this->authService->login($request->email, $request->password);
        
        if (!$user) {
            return $this->responseJSON(null, "failure", 401);
        }

        return $this->responseJSON($user);
    }

    public function register(Request $request): JsonResponse
    {
        $this->validateRequest($request, 'register');

        $user = $this->authService->register($request->name, $request->email, $request->password);
        return $this->responseJSON($user);
    }

<<<<<<< HEAD
    public function logout(): JsonResponse{
=======
    public function logout(): JsonResponse
    {
>>>>>>> 1a3e34bd8fe77bbd575e8a222cb42d55f1a808d3
        $this->authService->logout();
        return $this->responseJSON(null, "success");
    }

<<<<<<< HEAD
    public function refresh(): JsonResponse{
=======
    public function refresh(): JsonResponse
    {
>>>>>>> 1a3e34bd8fe77bbd575e8a222cb42d55f1a808d3
        $user = $this->authService->refresh();
        return $this->responseJSON($user);
    }

<<<<<<< HEAD
    public function me(): JsonResponse{
=======
    public function me(): JsonResponse
    {
>>>>>>> 1a3e34bd8fe77bbd575e8a222cb42d55f1a808d3
        $user = $this->authService->me();
        return $this->responseJSON($user);
    }

<<<<<<< HEAD
    public function getAllUsers(): JsonResponse{
=======
    public function getAllUsers(): JsonResponse
    {
>>>>>>> 1a3e34bd8fe77bbd575e8a222cb42d55f1a808d3
        $users = User::with(['role', 'household'])
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

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->authService->me();
<<<<<<< HEAD
        $this->validateRequest($request, 'updateProfile', ['user' => $user]);
=======
        
        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id)
            ],
        ]);
>>>>>>> 1a3e34bd8fe77bbd575e8a222cb42d55f1a808d3

        $user = $this->authService->updateProfile($request->all());
        return $this->responseJSON($user);
    }
}

