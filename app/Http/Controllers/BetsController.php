<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bet;
use App\Models\User;
use App\Models\UserHierarchy;

class BetsController extends Controller
{
    /**
     * Get bets for a player within the user's hierarchy.
     */
    public function getPlayerBets(Request $request, $userId)
    {
        $authenticatedUser = $request->user();
    
        // Validate filters
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'type' => 'nullable|in:slot,live',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
    
        // Ensure the target user is in the hierarchy or is the authenticated user
        if ($authenticatedUser->id !== (int) $userId) {
            $isInHierarchy = UserHierarchy::where('ancestorId', $authenticatedUser->id)
                ->where('descendantId', $userId)
                ->exists();
    
            if (!$isInHierarchy) {
                return response()->json(['status' => 'fail', 'error' => 'Unauthorized: Player is not in your hierarchy'], 403);
            }
        }
    
        // Fetch the player and validate role hierarchy
        $player = User::with('role')->where('id', $userId)->first();
    
        if (!$player || !$this->isRoleDescendant($player->role, 'Player')) {
            return response()->json([
                'status' => 'fail',
                'error' => 'Player not found or unauthorized access',
            ], 403);
        }
    
        // Apply filters to the bets query
        $betsQuery = Bet::query()
            ->where('user_id', $player->id)
            ->when(!empty($filters['from_date']), fn($q) => $q->whereDate('created_at', '>=', $filters['from_date']))
            ->when(!empty($filters['to_date']), fn($q) => $q->whereDate('created_at', '<=', $filters['to_date']))
            ->when(!empty($filters['type']), fn($q) => $q->where('type', $filters['type']))
            ->orderBy('created_at', 'desc');
    
        // Get paginated bets
        $bets = $betsQuery->paginate($filters['per_page'] ?? 10);
    
        return response()->json([
            'current_page' => $bets->currentPage(),
            'per_page' => $bets->perPage(),
            'total' => $bets->total(),
            'data' => $bets->items(),
        ], 200);
    }
    
    /**
     * Check if a role is a descendant of a specific role by name.
     */
    private function isRoleDescendant($role, $roleName)
    {
        while ($role) {
            if ($role->name === $roleName) {
                return true;
            }
            $role = $role->parent;
        }
        return false;
    }     
}
