<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\SiteSettings;

class AuthController extends Controller
{
    /**
     * Login a user and return a token.
     */
    public function login(Request $request)
    {
        // Validate the request
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Find the user by username
        $user = User::where('username', $credentials['username'])->first();

        // Check if the user exists and the password is valid
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Check if the user is blocked
        if (!$user->status) {
            return response()->json(['message' => 'User is blocked'], 403);
        }

        // Prevent users with the "Player" role from logging in via this endpoint
        if ($user->role->name === 'Player') {
            return response()->json(['message' => 'Players are not allowed to login'], 403);
        }

        // Generate a new token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Update last accessed time
        $user->update(['last_accessed' => now()]);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
        ], 200);
    }

    /**
     * Logout the authenticated user.
     */
    public function logout(Request $request)
    {
        // Revoke the current access token
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    /**
     * Get the authenticated user's details.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $siteSettings = SiteSettings::first();

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'balance' => $user->balance,
            'role' => $user->role->name,
            'currency' => $siteSettings ? $siteSettings->currency : null,
        ], 200);
    }
}
