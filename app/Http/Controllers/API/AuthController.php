<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/*
 * AuthController handles registration, login, logout, and profile.
 *
 * HOW SANCTUM AUTH WORKS:
 * 1. User logs in → Laravel creates a token and returns it
 * 2. Frontend stores that token
 * 3. Every future request sends the token in the Authorization header
 * 4. Laravel reads the token and knows who the user is
 */
class AuthController extends Controller
{
    // POST /api/register
    public function register(Request $request)
    {
        // validate() automatically returns a 422 error if rules fail
        $request->validate([
            'nom' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'mot_de_passe' => 'required|string|min:6',
        ]);

        $user = User::create([
            'nom' => $request->nom,
            'email' => $request->email,
            'mot_de_passe' => Hash::make($request->mot_de_passe), // never store plain text!
            'role' => 'client', // always force client role on registration
        ]);

        // Create a Sanctum token for the new user
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    // POST /api/login
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'mot_de_passe' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        // Hash::check() compares the plain password with the stored hash
        if (! $user || ! Hash::check($request->mot_de_passe, $user->mot_de_passe)) {
            return response()->json(['message' => 'Incorrect email or password'], 401);
        }

        // Delete old tokens (single session) and create a new one
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    // POST /api/logout — requires auth
    public function logout(Request $request)
    {
        // Delete only the current token being used
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout successful']);
    }

    // GET /api/user — returns the currently logged-in user
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
