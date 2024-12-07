<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Models\UserHierarchy;

class UserController extends Controller
{
    /**
     * Create a new user.
     */
    public function createUser(Request $request)
    {
        $authenticatedUser = $request->user();
        $authenticatedUserRole = $authenticatedUser->role;
        $directChildRole = Role::where('parent_id', $authenticatedUserRole->id)->pluck('id')->toArray();

        // Validate the request
        $data = $request->validate([
            'username' => 'required|string|alpha_num|unique:users,username',
            'password' => 'required|string',
            'roleId' => 'required|integer|exists:roles,id',
            'parentId' => 'nullable|integer|exists:users,id',
        ]);

        // Check if the role to be created is allowed under the current user's role
        if (!in_array($data['roleId'], $directChildRole)) {
            // If not directly under their role, ensure they specify a valid parentId
            if (empty($data['parentId'])) {
                return response()->json(['message' => 'Parent user ID is required for this role'], 403);
            }

            $parentUser = User::find($data['parentId']);

            if (!$parentUser || $parentUser->role->id != Role::find($data['roleId'])->parent_id) {
                return response()->json(['message' => 'Invalid parent user ID or parent role mismatch'], 403);
            }

            // Ensure the parent user is in the authenticated user's hierarchy
            if (!UserHierarchy::where('ancestorId', $authenticatedUser->id)->where('descendantId', $parentUser->id)->exists()) {
                return response()->json(['message' => 'Parent user is not in your hierarchy'], 403);
            }
        }

        // Default to making the authenticated user the parent if no parentId is provided
        if (empty($data['parentId'])) {
            $data['parentId'] = $authenticatedUser->id;
        }

        // Create the user
        $newUser = User::create([
            'username' => $data['username'],
            'password' => bcrypt($data['password']),
            'roleId' => $data['roleId'],
            'parentId' => $data['parentId'],
            'balance' => 0,
            'status' => true,
        ]);

        // Add the user to the hierarchy (self-reference)
        if (!UserHierarchy::where('ancestorId', $authenticatedUser->id)->where('descendantId', $newUser->id)->exists()) {
            UserHierarchy::create([
                'ancestorId' => $authenticatedUser->id,
                'descendantId' => $newUser->id,
                'depth' => 1,
            ]);
        }

        // Copy all ancestors of the parent to the new user
        $hierarchy = UserHierarchy::where('descendantId', $authenticatedUser->id)->get();
        foreach ($hierarchy as $ancestor) {
            if (!UserHierarchy::where('ancestorId', $ancestor->ancestorId)->where('descendantId', $newUser->id)->exists()) {
                UserHierarchy::create([
                    'ancestorId' => $ancestor->ancestorId,
                    'descendantId' => $newUser->id,
                    'depth' => $ancestor->depth + 1,
                ]);
            }
        }

        return response()->json(['message' => 'User created successfully', 'user' => $newUser], 201);
    }

    /**
     * Get users by role.
     */
    public function getUsersByRole(Request $request, $roleId)
    {
        $authenticatedUser = $request->user();

        // Validate the role
        $role = Role::find($roleId);
        if (!$role) {
            return response()->json(['message' => 'Invalid role ID'], 404);
        }

        // Get users in the role within the authenticated user's hierarchy
        $userIds = UserHierarchy::where('ancestorId', $authenticatedUser->id)->pluck('descendantId');
        $users = User::whereIn('id', $userIds)->where('roleId', $roleId)->get();

        return response()->json(['users' => $users], 200);
    }
}
