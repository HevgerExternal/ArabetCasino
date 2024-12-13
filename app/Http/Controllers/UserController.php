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
            'password' => 'required|string|min:6',
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
     * Get users by role with pagination, optionally filtering by date range and username.
     */
    public function getUsersByRole(Request $request, $roleId)
    {
        $authenticatedUser = $request->user();

        // Validate the role
        $role = Role::find($roleId);
        if (!$role) {
            return response()->json(['message' => 'Invalid role ID'], 404);
        }

        // Validate optional parameters
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $search = $request->query('search');
        $perPage = $request->query('per_page', 10); // Default to 10 if not provided

        if (($fromDate && !strtotime($fromDate)) || ($toDate && !strtotime($toDate))) {
            return response()->json(['message' => 'Invalid date format for "from" or "to" parameter'], 400);
        }

        // Get users in the role within the authenticated user's hierarchy
        $userIds = UserHierarchy::where('ancestorId', $authenticatedUser->id)->pluck('descendantId');
        $query = User::whereIn('id', $userIds)
            ->where('roleId', $roleId);

        // Apply date filters if provided
        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('created_at', '<=', $toDate);
        }

        // Apply search filter if provided
        if ($search) {
            $query->where('username', 'like', '%' . $search . '%');
        }

        // Validate per_page parameter to prevent excessive data load
        $perPage = is_numeric($perPage) && $perPage > 0 ? (int)$perPage : 10;

        // Paginate the results using per_page
        $users = $query->paginate($perPage);

        // Add parent username and subnet balance to each user in the response
        $users->getCollection()->transform(function ($user) {
            $descendantIds = UserHierarchy::where('ancestorId', $user->id)->pluck('descendantId');
            $subnetBalance = User::whereIn('id', $descendantIds)->sum('balance');

            $user->subnet = $subnetBalance;
            $user->parentUsername = $user->parent ? $user->parent->username : null;
            return $user;
        });

        return response()->json([
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
            'data' => $users->items(),
        ], 200);
    }

    /**
     * Search users by username, with optional role filtering, within hierarchy.
     */
    public function searchUsers(Request $request)
    {
        $authenticatedUser = $request->user();

        // Validate the input
        $request->validate([
            'username' => 'required|string',
            'roleId' => 'nullable|integer|exists:roles,id', // Role is optional
        ]);

        // Get descendant IDs from hierarchy
        $userIds = UserHierarchy::where('ancestorId', $authenticatedUser->id)->pluck('descendantId');

        // Base query
        $query = User::whereIn('id', $userIds)
            ->where('username', 'like', '%' . $request->username . '%');

        // Optional role filter
        if ($request->has('roleId')) {
            $query->where('roleId', $request->roleId);
        }

        // Paginate the results
        $users = $query->paginate(10);

        return response()->json([
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
            'data' => $users->items(),
        ], 200);
    }

    /**
     * Change the status of a user.
     */
    public function changeStatus(Request $request, $userId)
    {
        $authenticatedUser = $request->user();

        // Validate the input
        $request->validate([
            'status' => 'required|boolean',
        ]);

        $user = User::find($userId);

        // Ensure the user exists
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Ensure the target user is in the hierarchy
        $isInHierarchy = UserHierarchy::where('ancestorId', $authenticatedUser->id)
            ->where('descendantId', $user->id)
            ->exists();

        if (!$isInHierarchy) {
            return response()->json(['message' => 'Unauthorized: User is not in your hierarchy'], 403);
        }

        // Ensure the target user is in a lower role
        if ($authenticatedUser->role->id >= $user->role->id) {
            return response()->json(['message' => 'Unauthorized: Cannot change status for users of equal or higher role'], 403);
        }

        // Update the status
        $user->update(['status' => $request->status]);

        return response()->json(['message' => 'User status updated successfully'], 200);
    }

    /**
     * Change the password of a user.
     */
    public function changePassword(Request $request, $userId)
    {
        $authenticatedUser = $request->user();

        // Validate the input
        $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user = User::find($userId);

        // Ensure the user exists
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check if the authenticated user is the target user or in the hierarchy
        $isInHierarchy = UserHierarchy::where('ancestorId', $authenticatedUser->id)
            ->where('descendantId', $user->id)
            ->exists();

        if ($authenticatedUser->id !== $user->id && !$isInHierarchy) {
            return response()->json(['message' => 'Unauthorized: Cannot change password for this user'], 403);
        }

        // Update the password
        $user->update(['password' => bcrypt($request->password)]);

        return response()->json(['message' => 'Password updated successfully'], 200);
    }

    /**
     * Retrieve the hierarchy tree for the authenticated user.
     */
    public function getHierarchyTree(Request $request)
    {
        $authenticatedUser = $request->user();

        // Recursive function to build the hierarchy tree
        function buildTree($userId)
        {
            // Get all direct descendants of the user
            $children = User::where('parentId', $userId)->get();

            // Recursively build the tree for each child
            $tree = [];
            foreach ($children as $child) {
                $tree[] = [
                    'id' => $child->id,
                    'username' => $child->username,
                    'role' => $child->role->name,
                    'children' => buildTree($child->id), // Recursive call for children
                ];
            }

            return $tree;
        }

        // Build the hierarchy tree starting from the authenticated user
        $hierarchyTree = [
            'id' => $authenticatedUser->id,
            'username' => $authenticatedUser->username,
            'role' => $authenticatedUser->role->name,
            'children' => buildTree($authenticatedUser->id),
        ];

        return response()->json($hierarchyTree, 200);
    }

    /**
     * Get a user by ID if they are within the authenticated user's hierarchy.
     */
    public function getUserById(Request $request, $userId)
    {
        $authenticatedUser = $request->user();

        // Ensure the target user is in the hierarchy
        $isInHierarchy = UserHierarchy::where('ancestorId', $authenticatedUser->id)
            ->where('descendantId', $userId)
            ->exists();

        if (!$isInHierarchy) {
            return response()->json(['message' => 'Unauthorized: User is not in your hierarchy'], 403);
        }

        // Find the user
        $user = User::find($userId);

        // Ensure the user exists
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Return the user details
        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->role->name,
            'parentId' => $user->parentId,
            'status' => $user->status,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ], 200);
    }
}
