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
        $user->token = $token;
        return $user;
    }

    function me()
    {
        return Auth::guard('api')->user();
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

