<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | REGISTER
    |--------------------------------------------------------------------------
    */

    public function register(Request $request)
    {
        $validated = $request->validate([

            'name' => 'required|string|max:255',

            'email' => 'required|email|unique:users,email',

            'gender' => 'required|in:B,G',

            'password' => 'required|min:6|confirmed',

        ]);

        $user = User::create([

            'name' => $validated['name'],

            'email' => $validated['email'],

            'gender' => $validated['gender'],

            'password' => Hash::make(
                $validated['password']
            ),

        ]);

        $token = $user->createToken(
            'auth_token'
        )->plainTextToken;

        return response()->json([

            'success' => true,

            'message' => 'User registered successfully',

            'token' => $token,

            'user' => $user,

        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */

    public function login(Request $request)
    {
        $validated = $request->validate([

            'email' => 'required|email',

            'password' => 'required',

        ]);

        $user = User::where(
            'email',
            $validated['email']
        )->first();

        if (
            !$user ||
            !Hash::check(
                $validated['password'],
                $user->password
            )
        ) {

            return response()->json([

                'success' => false,

                'message' => 'Invalid credentials',

            ], 401);
        }

        $token = $user->createToken(
            'auth_token'
        )->plainTextToken;

        return response()->json([

            'success' => true,

            'message' => 'Login successful',

            'token' => $token,

            'user' => $user,

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request)
    {
        $request->user()
            ->currentAccessToken()
            ->delete();

        return response()->json([

            'success' => true,

            'message' => 'Logged out successfully',

        ]);
    }
}