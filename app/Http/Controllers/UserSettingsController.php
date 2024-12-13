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
     * Get roles under the logged-in user's role, including parent role details.
     */
    public function getRolesUnderUser(Request $request)
    {
        $authenticatedUser = $request->user();

        // Validate optional parameter
        $includeSelf = filter_var($request->query('include_self', false), FILTER_VALIDATE_BOOLEAN);

        // Get the authenticated user's role
        $currentRoleId = $authenticatedUser->roleId;

        // Build the query
        $rolesUnderQuery = Role::query()
            ->with('parent:id,name') // Include the parent role (only id and name)
            ->select(['id as roleId', 'name as roleName', 'parent_id']);

        // Adjust the query based on include_self
        if ($includeSelf) {
            $rolesUnderQuery->where('id', '>=', $currentRoleId); // Include current role
        } else {
            $rolesUnderQuery->where('id', '>', $currentRoleId); // Exclude current role
        }

        // Fetch roles
        $rolesUnder = $rolesUnderQuery->get();

        // Add requiresParent property and format parent role
        $rolesUnder = $rolesUnder->map(function ($role, $index) use ($includeSelf) {
            // Set requiresParent logic: false for the first role, true for others
            $role->requiresParent = $index === 0 ? false : true;
            $role->parentRole = $role->parent ? [
                'roleId' => $role->parent->id,
                'roleName' => $role->parent->name,
            ] : null;
            unset($role->parent);
            return $role;
        });

        return response()->json($rolesUnder, 200);
    }
}
