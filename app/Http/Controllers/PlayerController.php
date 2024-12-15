<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\SiteSettings;
use App\Models\User;

class PlayerController extends Controller
{

    /**
     * Login a player user and return a token.
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

        // Restrict login to players only
        if ($user->role->name !== 'Player') {
            return response()->json(['message' => 'Only players can login here'], 403);
        }

        // Generate a new token with player abilities
        $token = $user->createToken('auth_token', ['player'])->plainTextToken;

        // Update last accessed time
        $user->update(['last_accessed' => now()]);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
        ], 200);
    }

    /**
     * Get the authenticated player's details.
     */
    public function me(Request $request)
    {
        $player = $request->user();
        $siteSettings = SiteSettings::first();

        return response()->json([
            'id' => $player->id,
            'username' => $player->username,
            'balance' => $player->balance, 
            'currency' => $siteSettings ? $siteSettings->currency : null,
        ]);
    }
}
