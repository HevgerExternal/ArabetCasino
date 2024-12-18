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

        // Define allowed roles for `showBetInfo`
        $allowedRoles = ['Root', 'Admin', 'Manager'];

        // Map bets data to include conditional `showBetInfo`
        $data = $bets->map(function ($bet) use ($authenticatedUser, $allowedRoles) {
            $showBetInfo = false;

            if (
                in_array($authenticatedUser->role->name, $allowedRoles) &&
                $bet->type === 'live' &&
                $bet->provider === 'nexus'
            ) {
                $showBetInfo = true;
            }

            return [
                'id' => $bet->id,
                'bet_amount' => $bet->bet_amount,
                'win_amount' => $bet->win_amount,
                'type' => $bet->type,
                'provider' => $bet->provider,
                'created_at' => $bet->created_at,
                'updated_at' => $bet->updated_at,
                'show_bet_info' => $showBetInfo,
            ];
        });

        return response()->json([
            'current_page' => $bets->currentPage(),
            'per_page' => $bets->perPage(),
            'total' => $bets->total(),
            'data' => $data,
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

    public function visualizeBet(Request $request, $betId)
    {
        $authenticatedUser = $request->user();
    
        // Fetch the user's role and ensure it's Manager or above
        $allowedRoles = ['Root', 'Admin', 'Manager'];
        if (!in_array($authenticatedUser->role->name, $allowedRoles)) {
            return response()->json([
                'status' => 'fail',
                'error' => 'Unauthorized access. This action is restricted to Manager and above.',
            ], 403);
        }
    
        // Fetch the bet by ID and ensure it exists
        $bet = Bet::findOrFail($betId);
    
        // Check if the provider is Nexus
        if ($bet->provider !== 'nexus') {
            return response()->json([
                'status' => 'fail',
                'error' => 'Only Nexus bets can be visualized',
            ], 400);
        }
    
        // Determine the format of the bet's info field (JSON or XML)
        $data = null;
        $type = null;
    
        if ($this->isJson($bet->info)) {
            $data = json_decode($bet->info, true);
            $type = 'json';
        } elseif ($this->isXml($bet->info)) {
            $data = $this->xmlToArray($bet->info);
            $type = 'xml';
        } else {
            return response()->json([
                'status' => 'fail',
                'error' => 'Unsupported data format',
            ], 400);
        }
    
        return response()->json([
            'status' => 'success',
            'type' => $type,
            'data' => $data,
        ], 200);
    }    

    /**
     * Check if a string is valid JSON.
     */
    private function isJson($string)
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    /**
     * Check if a string is valid XML.
     */
    private function isXml($string)
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($string);
        return $xml !== false;
    }

    /**
     * Convert an XML string to an array.
     */
    private function xmlToArray($xmlString)
    {
        $xml = simplexml_load_string($xmlString);
        $json = json_encode($xml);
        return json_decode($json, true);
    }
}
