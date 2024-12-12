<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserHierarchy;
use App\Models\SiteSettings;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics(Request $request)
    {
        $authenticatedUser = $request->user();
        $siteSettings = SiteSettings::first();

        // Get all users in the hierarchy including the authenticated user
        $userIds = $this->getUserHierarchyIds($authenticatedUser);

        $data = [
            'total_balance' => $this->calculateTotalBalance($userIds),
            'total_players' => $this->countPlayers($userIds),
            'total_users' => $this->countNonPlayers($userIds),
            'total_bet' => 0, // Placeholder for future implementation
            'total_win' => 0, // Placeholder for future implementation
            'total_ggr' => 0, // Placeholder for future implementation
            'currency' => $siteSettings ? $siteSettings->currency : null,
        ];
        return response()->json($data);
    }

    /**
     * Get all user IDs in the hierarchy, including the authenticated user.
     *
     * @param \App\Models\User $user
     * @return \Illuminate\Support\Collection
     */
    private function getUserHierarchyIds($user)
    {
        $userIds = UserHierarchy::where('ancestorId', $user->id)->pluck('descendantId');
        $userIds->push($user->id);
        return $userIds;
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
}
