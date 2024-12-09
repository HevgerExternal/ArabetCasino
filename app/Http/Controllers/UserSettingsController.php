<?php

namespace App\Http\Controllers;

use App\Models\UserSettings;
use App\Models\Role;
use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
    /**
     * Get the settings for the authenticated user.
     */
    public function getSettings(Request $request)
    {
        $user = $request->user();

        return response()->json($user->settings, 200);
    }

    /**
     * Update the settings for the authenticated user.
     */
    public function updateSettings(Request $request)
    {
        $user = $request->user();

        // Validate the input
        $validated = $request->validate([
            'numberFormat' => 'nullable|in:Short,Large',
            'useThousandSeparator' => 'nullable|boolean',
        ]);

        // Update settings
        $user->settings()->updateOrCreate([], $validated);

        return response()->json(['message' => 'Settings updated successfully', 'settings' => $user->settings], 200);
    }

     /**
     * Get roles under the logged-in user's role.
     */
    public function getRolesUnderUser(Request $request)
    {
        $authenticatedUser = $request->user();

        // Get the authenticated user's role
        $currentRoleId = $authenticatedUser->roleId;

        // Fetch roles below the current user's role
        $rolesUnder = Role::where('id', '>', $currentRoleId)
            ->get(['id as roleId', 'name as roleName']);

        return response()->json($rolesUnder, 200);
    }
}
