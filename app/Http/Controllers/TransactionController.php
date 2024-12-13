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
     * Get transactions for a specific user with deposit/withdrawal summary.
     */
    public function getUserTransactions(Request $request, $userId)
    {
        $authenticatedUser = $request->user();

        // Validate filters
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'type' => 'nullable|in:deposit,withdraw', // Add type filter
            'per_page' => 'nullable|integer|min:1|max:100', // Add validation for per_page
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
        $query = Transaction::query();

        if (!empty($filters['type'])) {
            $query->where(function ($subQuery) use ($userId, $filters) {
                $subQuery->where('fromUserId', $userId)
                    ->where('type', $filters['type'])
                    ->orWhere(function ($nestedQuery) use ($userId, $filters) {
                        $nestedQuery->where('toUserId', $userId)
                            ->where('type', $filters['type']);
                    });
            });
        } else {
            $query->where('fromUserId', $userId)
                ->orWhere('toUserId', $userId);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('date', '<=', $filters['to_date']);
        }

        // Order by created_at in descending order (latest first)
        $query->orderBy('created_at', 'desc');

        // Get per_page value from the request, default to 10
        $perPage = $filters['per_page'] ?? 10;

        // Validate per_page parameter to prevent excessive data load
        $perPage = is_numeric($perPage) && $perPage > 0 ? (int)$perPage : 10;

        // Get transactions with pagination
        $transactions = $query->paginate($perPage);

        // Calculate sum of deposits and withdrawals
        $depositSum = Transaction::where('toUserId', $userId)
            ->where('type', 'deposit')
            ->sum('amount');

        $withdrawalSum = Transaction::where('fromUserId', $userId)
            ->where('type', 'withdraw')
            ->sum('amount');

        $netAmount = $depositSum - $withdrawalSum;

        // Return response with transaction summary
        return response()->json([
            'current_page' => $transactions->currentPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
            'data' => $transactions->items(),
            'summary' => [
                'total_deposit' => $depositSum,
                'total_withdrawal' => $withdrawalSum,
                'net_amount' => $netAmount,
            ],
        ], 200);
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
            'per_page' => 'nullable|integer|min:1|max:100', // Add validation for per_page
        ]);

        // Start the query
        $query = Transaction::query();

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

        // Order by created_at in descending order (latest first)
        $query->orderBy('created_at', 'desc');

        // Get per_page value from the request, default to 10
        $perPage = $filters['per_page'] ?? 10;

        // Validate per_page parameter to prevent excessive data load
        $perPage = is_numeric($perPage) && $perPage > 0 ? (int)$perPage : 10;

        $transactions = $query->paginate($perPage);

        // Return response
        return response()->json([
            'current_page' => $transactions->currentPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
            'data' => $transactions->items(),
        ], 200);
    }

}
