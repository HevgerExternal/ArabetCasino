<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Models\UserHierarchy;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Create a transaction.
     */
    public function createTransaction(Request $request)
    {
        $authenticatedUser = $request->user();

        // Validate input
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:deposit,withdraw',
            'toUserId' => 'required|exists:users,id',
        ]);

        $amount = $data['amount'];
        $type = $data['type'];
        $toUser = User::find($data['toUserId']);

        // Ensure the target user is in the hierarchy
        $isInHierarchy = UserHierarchy::where('ancestorId', $authenticatedUser->id)
            ->where('descendantId', $toUser->id)
            ->exists();

        if (!$isInHierarchy) {
            return response()->json(['message' => 'Unauthorized: User is not in your hierarchy'], 403);
        }

        // Ensure the transaction is downward (only for roles below)
        if ($authenticatedUser->role->id >= $toUser->role->id) {
            return response()->json(['message' => 'Unauthorized: Cannot transact upward'], 403);
        }

        // Perform the transaction
        if ($type === 'deposit') {
            // Subtract amount from authenticated user
            if ($authenticatedUser->balance < $amount) {
                return response()->json(['message' => 'Insufficient balance'], 400);
            }

            $authenticatedUser->decrement('balance', $amount);
            $toUser->increment('balance', $amount);
        } elseif ($type === 'withdraw') {
            // Subtract from the target user and add to the authenticated user
            if ($toUser->balance < $amount) {
                return response()->json(['message' => 'Target user has insufficient balance'], 400);
            }

            $toUser->decrement('balance', $amount);
            $authenticatedUser->increment('balance', $amount);
        }

        // Create the transaction record
        $transaction = Transaction::create([
            'amount' => $amount,
            'type' => $type,
            'fromUserId' => $authenticatedUser->id,
            'toUserId' => $toUser->id,
            'fromRole' => $authenticatedUser->role->name,
            'toRole' => $toUser->role->name,
            'fromUsername' => $authenticatedUser->username,
            'toUsername' => $toUser->username,
            'date' => now(),
        ]);

        return response()->json(['message' => 'Transaction successful', 'transaction' => $transaction], 201);
    }

    /**
     * Get all transactions with optional filters.
     */
    public function getTransactions(Request $request)
    {
        $authenticatedUser = $request->user();

        // Validate filters
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'from_role' => 'nullable|string',
            'to_role' => 'nullable|string',
            'type' => 'nullable|in:deposit,withdraw', // Add type filter
        ]);

        // Get descendant IDs from hierarchy
        $userIds = UserHierarchy::where('ancestorId', $authenticatedUser->id)->pluck('descendantId');

        // Apply filters
        $query = Transaction::whereIn('fromUserId', $userIds)
            ->orWhereIn('toUserId', $userIds);

        if (!empty($filters['from_date'])) {
            $query->whereDate('date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('date', '<=', $filters['to_date']);
        }
        if (!empty($filters['from_role'])) {
            $query->where('fromRole', $filters['from_role']);
        }
        if (!empty($filters['to_role'])) {
            $query->where('toRole', $filters['to_role']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $transactions = $query->paginate(10);

        return response()->json([
            'current_page' => $transactions->currentPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
            'data' => $transactions->items(),
        ], 200);
    }

    /**
     * Get transactions for a specific user.
     */
    public function getUserTransactions(Request $request, $userId)
    {
        $authenticatedUser = $request->user();

        // Validate filters
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'type' => 'nullable|in:deposit,withdraw', // Add type filter
        ]);

        // Ensure the target user is in the hierarchy or is the authenticated user
        if ($authenticatedUser->id !== (int) $userId) {
            $isInHierarchy = UserHierarchy::where('ancestorId', $authenticatedUser->id)
                ->where('descendantId', $userId)
                ->exists();

            if (!$isInHierarchy) {
                return response()->json(['message' => 'Unauthorized: User is not in your hierarchy'], 403);
            }
        }

        // Apply filters
        $query = Transaction::where('fromUserId', $userId)
            ->orWhere('toUserId', $userId);

        if (!empty($filters['from_date'])) {
            $query->whereDate('date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('date', '<=', $filters['to_date']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $transactions = $query->paginate(10);

        return response()->json([
            'current_page' => $transactions->currentPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
            'data' => $transactions->items(),
        ], 200);
    }
}
