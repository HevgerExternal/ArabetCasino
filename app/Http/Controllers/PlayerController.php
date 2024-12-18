<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\SiteSettings;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Bet;

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

    
   /**
     * Get transactions for the authenticated player with filters.
     */
    public function transactions(Request $request)
    {
        $authenticatedUser = $request->user();

        // Validate filters
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'type' => 'nullable|in:deposit,withdraw',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Apply filters
        $query = Transaction::query()
            ->where(function ($subQuery) use ($authenticatedUser) {
                $subQuery->where('fromUserId', $authenticatedUser->id)
                        ->orWhere('toUserId', $authenticatedUser->id);
            })
            ->when(!empty($filters['type']), fn($q) => $q->where('type', $filters['type']))
            ->when(!empty($filters['from_date']), fn($q) => $q->whereDate('date', '>=', $filters['from_date']))
            ->when(!empty($filters['to_date']), fn($q) => $q->whereDate('date', '<=', $filters['to_date']))
            ->orderBy('created_at', 'desc');

        // Get paginated transactions
        $transactions = $query->paginate($filters['per_page'] ?? 10);

        // Return response
        return response()->json([
            'current_page' => $transactions->currentPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
            'data' => $transactions->items(),
        ], 200);
    }

    /**
     * Get bets for the authenticated player with filters.
     */
    public function bets(Request $request)
    {
        $authenticatedUser = $request->user();

        // Validate filters
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Apply filters
        $query = Bet::query()
            ->where('user_id', $authenticatedUser->id)
            ->when(!empty($filters['from_date']), fn($q) => $q->whereDate('created_at', '>=', $filters['from_date']))
            ->when(!empty($filters['to_date']), fn($q) => $q->whereDate('created_at', '<=', $filters['to_date']))
            ->orderBy('created_at', 'desc');

        // Get paginated bets
        $bets = $query->paginate($filters['per_page'] ?? 10);

        // Return response
        return response()->json([
            'current_page' => $bets->currentPage(),
            'per_page' => $bets->perPage(),
            'total' => $bets->total(),
            'data' => $bets->items(),
        ], 200);
    }
}
