<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Bet;

class LvlGameCallbackController extends Controller
{
    public function handleCallback(Request $request)
    {
        $validated = $request->validate([
            'cmd' => 'required|string',
            'hall' => 'required|string',
            'key' => 'required|string',
        ]);

        if ($validated['hall'] !== '3205995' || $validated['key'] !== 'sky777testkey') {
            return response()->json(['status' => 'fail', 'error' => 'Invalid hall or key'], 403);
        }

        switch ($validated['cmd']) {
            case 'getBalance':
                return $this->handleGetBalance($request);
            case 'writeBet':
                return $this->handleWriteBet($request);
            default:
                return response()->json(['status' => 'fail', 'error' => 'Invalid command'], 400);
        }
    }

    private function handleGetBalance(Request $request)
    {
        $validated = $request->validate(['login' => 'required|string']);
        $user = User::where('username', $validated['login'])->first();

        if (!$user) {
            return response()->json(['status' => 'fail', 'error' => 'User not found'], 404);
        }

        return response()->json(['status' => 'success', 'balance' => $user->balance], 200);
    }

    private function handleWriteBet(Request $request)
    {
        $validated = $request->validate([
            'login' => 'required|string',
            'bet' => 'required|numeric',
            'win' => 'required|numeric',
            'tradeId' => 'nullable|string',
            'matrix' => 'nullable|string',
        ]);

        $user = User::where('username', $validated['login'])->first();

        if (!$user) {
            return response()->json(['status' => 'fail', 'error' => 'User not found'], 404);
        }

        if ($user->balance < $validated['bet']) {
            return response()->json(['status' => 'fail', 'error' => 'Insufficient balance'], 400);
        }

        $user->balance = $user->balance - $validated['bet'] + $validated['win'];
        $user->save();

        Bet::create([
            'user_id' => $user->id,
            'trade_id' => $validated['tradeId'] ?? null,
            'bet_amount' => $validated['bet'],
            'win_amount' => $validated['win'],
            'matrix' => $validated['matrix'] ?? null,
        ]);

        return response()->json(['status' => 'success', 'balance' => $user->balance], 200);
    }
}
