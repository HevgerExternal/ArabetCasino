<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserHierarchy;
use App\Models\SiteSettings;

class DashboardController extends Controller
{
    public function getStatistics(Request $request)
    {
        $authenticatedUser = $request->user();
        $siteSettings = SiteSettings::first();
    
        // Get all users in the hierarchy, including the authenticated user for balance
        $userIdsForBalance = $this->getUserHierarchyIds($authenticatedUser, true);
    
        // Get all users in the hierarchy, excluding the authenticated user for non-player and player counts
        $userIdsWithoutSelf = $this->getUserHierarchyIds($authenticatedUser, false);
    
        $data = [
            'total_balance' => $this->calculateTotalBalance($userIdsForBalance),
            'total_players' => $this->countPlayers($userIdsWithoutSelf),
            'total_users' => $this->countNonPlayers($userIdsWithoutSelf),
            'total_player_balance' => $this->calculatePlayerBalance($userIdsWithoutSelf),
            'total_bet' => 0, // Placeholder for future implementation
            'total_win' => 0, // Placeholder for future implementation
            'total_ggr' => 0, // Placeholder for future implementation
            'currency' => $siteSettings ? $siteSettings->currency : null,
        ];
        return response()->json($data);
    }
    
    
    /**
     * Get all user IDs in the hierarchy, optionally including the authenticated user.
     *
     * @param \App\Models\User $user
     * @param bool $includeSelf
     * @return \Illuminate\Support\Collection
     */
    private function getUserHierarchyIds($user, $includeSelf = true)
    {
        $userIds = UserHierarchy::where('ancestorId', $user->id)->pluck('descendantId');
        if ($includeSelf) {
            $userIds->push($user->id);
        }
        return $userIds;
    }

    /**
     * Get the total balance of players in the hierarchy.
     *
     * @param \Illuminate\Support\Collection $userIds
     * @return float
     */
    private function calculatePlayerBalance($userIds)
    {
        return User::whereIn('id', $userIds)
            ->whereHas('role', function ($query) {
                $query->where('name', '=', 'Player');
            })
            ->sum('balance');
    }

    /**
     * Count the number of non-player users in the hierarchy.
     *
     * @param \Illuminate\Support\Collection $userIds
     * @return int
     */
    private function countNonPlayers($userIds)
    {
        return User::whereIn('id', $userIds)
            ->whereHas('role', function ($query) {
                $query->where('name', '!=', 'Player');
            })
            ->count();
    }
    
    /**
     * Count the number of players in the hierarchy.
     *
     * @param \Illuminate\Support\Collection $userIds
     * @return int
     */
    private function countPlayers($userIds)
    {
        return User::whereIn('id', $userIds)
            ->whereHas('role', function ($query) {
                $query->where('name', '=', 'Player');
            })
            ->count();
    }
    
    /**
     * Calculate the total balance of all users in the hierarchy.
     *
     * @param \Illuminate\Support\Collection $userIds
     * @return float
     */
    private function calculateTotalBalance($userIds)
    {
        return User::whereIn('id', $userIds)->sum('balance');
    }    
}
