<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Bet;

class NexusCallbackController extends Controller
{
    public function handleCallback(Request $request)
    {
        try {
            $validated = $request->validate([
                'method' => 'required|string',
                'agent_code' => 'required|string',
                'agent_secret' => 'required|string',
                'user_code' => 'required|string',
            ]);

            if ($validated['agent_code'] !== config('services.nexus.agent_code') ||
                $validated['agent_secret'] !== config('services.nexus.agent_secret')) {
                return response()->json([
                    'status' => 0,
                    'msg' => 'INVALID_AGENT',
                ], 403);
            }

            switch ($validated['method']) {
                case 'user_balance':
                    return $this->handleUserBalance($validated);

                case 'transaction':
                    return $this->handleTransaction($request);

                default:
                    return response()->json([
                        'status' => 0,
                        'msg' => 'INVALID_METHOD',
                    ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error handling gold_api request:', ['exception' => $e]);

            return response()->json([
                'status' => 0,
                'msg' => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    private function handleUserBalance(array $data)
    {
        $user = User::where('username', $data['user_code'])->first();

        if (!$user) {
            Log::warning('User not found for user_code:', ['user_code' => $data['user_code']]);
            return response()->json([
                'status' => 0,
                'user_balance' => 0,
                'msg' => 'USER_NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'status' => 1,
            'user_balance' => $user->balance ?? 0,
        ], 200);
    }
    
    private function handleTransaction(Request $request)
    {
        $data = $request->validate([
            'agent_code' => 'required|string',
            'agent_secret' => 'required|string',
            'user_code' => 'required|string',
            'game_type' => 'required|string',
            'slot.provider_code' => 'nullable|string',
            'slot.game_code' => 'nullable|string',
            'slot.bet_money' => 'nullable|numeric',
            'slot.win_money' => 'nullable|numeric',
            'slot.txn_id' => 'nullable|string',
            'slot.txn_type' => 'nullable|string|in:debit,credit,debit_credit',
            'live.provider_code' => 'nullable|string',
            'live.game_code' => 'nullable|string',
            'live.bet_money' => 'nullable|numeric',
            'live.win_money' => 'nullable|numeric',
            'live.txn_id' => 'nullable|string',
            'live.txn_type' => 'nullable|string|in:debit,credit,debit_credit',
            'info' => 'nullable|string',
        ]);
    
        $user = User::where('username', $data['user_code'])->first();
    
        if (!$user) {
            Log::warning('User not found for user_code:', ['user_code' => $data['user_code']]);
            return response()->json([
                'status' => 0,
                'msg' => 'USER_NOT_FOUND',
            ], 404);
        }
    
        $gameData = $data[$data['game_type']] ?? [];
        $txnType = $gameData['txn_type'] ?? null;
        $tradeId = $gameData['txn_id'] ?? null;
        $betMoney = $gameData['bet_money'] ?? 0;
        $winMoney = $gameData['win_money'] ?? 0;
    
        if ($txnType === 'debit' && $user->balance < $betMoney) {
            return response()->json([
                'status' => 0,
                'msg' => 'INSUFFICIENT_USER_FUNDS',
            ], 400);
        }
    
        $newBalance = $user->balance;
    
        if ($txnType === 'debit') {
            $newBalance -= $betMoney;
        } elseif ($txnType === 'credit') {
            $newBalance += $winMoney;
        } elseif ($txnType === 'debit_credit') {
            $newBalance = $newBalance - $betMoney + $winMoney;
        }
    
        $user->balance = $newBalance;
        $user->save();
    
        $existingBet = Bet::where('trade_id', $tradeId)->first();
    
        if ($existingBet) {
            $existingBet->update([
                'win_amount' => $winMoney,
                'info' => $data['info'] ?? $existingBet->info,
            ]);
        } else {
            Bet::create([
                'user_id' => $user->id,
                'trade_id' => $tradeId,
                'bet_amount' => $betMoney,
                'win_amount' => $winMoney,
                'type' => $data['game_type'],
                'provider' => "nexus",
                'info' => $data['info'] ?? '',
            ]);
        }
    
        return response()->json([
            'status' => 1,
            'user_balance' => $user->balance,
        ], 200);
    }    
}
