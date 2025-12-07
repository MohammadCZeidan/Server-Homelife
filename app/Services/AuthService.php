<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    function login($email, $password)
    {
        $credentials = ['email' => $email, 'password' => $password];
        $token = Auth::guard('api')->attempt($credentials);

        if (!$token) {
            return null;
        }

        $user = Auth::guard('api')->user();
        $user->load(['role', 'household']);
        $user->token = $token;
        return $user;
    }

    function register($name, $email, $password)
    {
        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->save();

        $token = Auth::guard('api')->login($user);
        $user->load(['role', 'household']);
        $user->token = $token;
        return $user;
    }

    function logout()
    {
        Auth::guard('api')->logout();
        return true;
    }

    function refresh()
    {
        $user = Auth::guard('api')->user();
        $token = Auth::guard('api')->refresh();
        $user->load(['role', 'household']);
        $user->token = $token;
        return $user;
    }

    function me()
    {
        $user = Auth::guard('api')->user();
        if ($user) {
            $user->load(['role', 'household']);
        }
        return $user;
    }

    function updateProfile($data)
    {
        $user = Auth::guard('api')->user();
        
        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        
        if (isset($data['email'])) {
            $user->email = $data['email'];
        }
        
        $user->save();
        return $user;
    }
}
