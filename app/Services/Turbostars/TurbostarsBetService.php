<?php

namespace App\Services\Turbostars;

use Illuminate\Http\Request;
use App\Models\Bet;

class TurbostarsBetService extends TurbostarsBaseService
{
    public function handle($method, Request $request)
    {
        $type = $request->input('type');

        if ($method === 'post' && $type == 1) {
            return $this->placeBet($request);
        }

        if ($method === 'put') {
            return $this->handleBetOperations($request);
        }

        return response()->json(['status' => 'fail', 'error' => 'Invalid request'], 400);
    }

    protected function handleBetOperations(Request $request)
    {
        $type = $request->input('type');
        $transactionId = $request->input('transactionId');

        if ($type == 1) {
            return $this->unsettleBet($request);
        } elseif ($type == 2) {
            return $this->settleBet($request);
        } elseif ($type == 3) {
            return $this->rollbackBet($request);
        } else {
            return response()->json(['status' => 'fail', 'error' => 'Invalid type'], 400);
        }
    }

    protected function placeBet(Request $request)
    {
        $data = $this->validateRequest($request, [
            'token' => 'required|string',
            'transactionId' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string',
            'userId' => 'required|string',
            'type' => 'required|integer|in:1',
        ]);

        if ($data instanceof \Illuminate\Http\JsonResponse) {
            return $data;
        }

        $user = $this->getUserFromToken($data['token']);
        if (!$user || (string)$user->id !== $data['userId']) {
            return response()->json([
                'status' => 'fail',
                'error' => 'Invalid token or user not found',
                'code' => 3
            ], 400);
        }

        $existingBet = Bet::where('trade_id', $data['transactionId'])->first();
        if ($existingBet) {
            return response()->json([
                'status' => 'fail',
                'error' => 'Transaction already exists',
                'code' => 400
            ], 400);
        }

        if ($user->balance < $data['amount']) {
            return response()->json(['code' => 7, 'message' => 'Not enough money'], 400);
        }

        $user->balance -= $data['amount'];
        $user->save();

        Bet::create([
            'user_id' => $user->id,
            'trade_id' => $data['transactionId'],
            'bet_amount' => $data['amount'],
            'win_amount' => 0,
            'currency' => $data['currency'],
            'type' => 'sportsbook',
            'settle_status' => 'unsettled',
            'info' => json_encode(array_merge($data, ['resultType' => 'unsettled'])),
            'provider' => 'turbostars'
        ]);

        return response()->json([
            'transactionId' => $data['transactionId'],
            'transactionTime' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    protected function unsettleBet(Request $request)
    {
        $transactionId = $request->input('transactionId');
        $bet = Bet::where('trade_id', $transactionId)->first();

        if (!$bet) {
            return response()->json(['code' => 404, 'message' => 'Bet not found'], 404);
        }

        $user = $this->getUserFromToken($request->input('token'));
        if (!$user || (string)$user->id !== $request->input('userId')) {
            return response()->json([
                'status' => 'fail',
                'error' => 'Invalid token or user not found',
                'code' => 3
            ], 400);
        }

        $oldInfo = json_decode($bet->info, true);
        $oldResultType = $oldInfo['resultType'] ?? 'unsettled';
        $oldWinAmount = $bet->win_amount;

        if (in_array($oldResultType, ['refund', 'won', 'cashout'])) {
            $user->balance -= $oldWinAmount;
        } elseif ($oldResultType === 'rollback') {
            $user->balance -= $bet->bet_amount;
        }

        $bet->win_amount = 0;
        $bet->settle_status = 'unsettled';
        $oldInfo['resultType'] = 'unsettled';
        $bet->info = json_encode($oldInfo);

        $bet->save();
        $user->save();

        return response()->json([
            'transactionId' => $transactionId,
            'transactionTime' => gmdate('Y-m-d\TH:i:s\Z'),
        ], 200);
    }

    protected function settleBet(Request $request)
    {
        $data = $this->validateRequest($request, [
            'transactionId' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|min:3|max:5',
            'type' => 'required|integer|in:2',
            'userId' => 'required|string',
            'resultType' => 'required|string|in:won,lost,refund,cashout,unsettle,rollback',
            'token' => 'required|string',
        ]);

        if ($data instanceof \Illuminate\Http\JsonResponse) {
            return $data;
        }

        $user = $this->getUserFromToken($data['token']);
        if (!$user || (string)$user->id !== $data['userId']) {
            return response()->json([
                'status' => 'fail',
                'error' => 'Invalid token or user not found',
                'code' => 3
            ], 400);
        }

        $bet = Bet::where('trade_id', $data['transactionId'])->first();
        if (!$bet) {
            return response()->json(['code' => 404, 'message' => 'Bet not found'], 404);
        }

        $oldInfo = json_decode($bet->info, true);
        $oldResultType = $oldInfo['resultType'] ?? 'unsettled';
        $oldWinAmount = $bet->win_amount;
        $betAmount = $bet->bet_amount;
        $newResultType = $data['resultType'];
        $newAmount = $data['amount'];

        if ($oldResultType === $newResultType && $oldWinAmount == $newAmount) {
            return response()->json([
                'transactionId' => $data['transactionId'],
                'transactionTime' => gmdate('Y-m-d\TH:i:s\Z'),
            ], 200);
        }

        if ($oldResultType !== 'unsettled') {
            if (in_array($oldResultType, ['won', 'refund', 'cashout'])) {
                $user->balance -= $oldWinAmount;
            } elseif ($oldResultType === 'rollback') {
                $user->balance -= $betAmount;
            }
        }

        if (in_array($newResultType, ['won', 'refund', 'cashout'])) {
            if ($newAmount > 0) {
                $user->balance += $newAmount;
            }
            $bet->win_amount = $newAmount;
            $bet->settle_status = 'settled';
        } elseif ($newResultType === 'lost') {
            $bet->win_amount = 0;
            $bet->settle_status = 'settled';
        } elseif ($newResultType === 'unsettle') {
            $bet->win_amount = 0;
            $bet->settle_status = 'unsettled';
        } elseif ($newResultType === 'rollback') {
            $user->balance += $betAmount;
            $bet->win_amount = 0;
            $bet->settle_status = 'rolled_back';
        }

        $oldInfo['resultType'] = $newResultType;
        $bet->info = json_encode($oldInfo);

        $bet->save();
        $user->save();

        return response()->json([
            'transactionId' => $data['transactionId'],
            'transactionTime' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    protected function rollbackBet(Request $request)
    {
        $data = $this->validateRequest($request, [
            'transactionId' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|min:3|max:5',
            'type' => 'required|integer|in:3',
            'userId' => 'required|string',
            'token' => 'required|string',
        ]);

        if ($data instanceof \Illuminate\Http\JsonResponse) {
            return $data;
        }

        $user = $this->getUserFromToken($data['token']);
        if (!$user || (string)$user->id !== $data['userId']) {
            return response()->json([
                'status' => 'fail',
                'error' => 'Invalid token or user not found',
                'code' => 3
            ], 400);
        }

        $bet = Bet::where('trade_id', $data['transactionId'])->first();
        if (!$bet) {
            return response()->json(['code' => 404, 'message' => 'Bet not found'], 404);
        }

        if ($bet->settle_status === 'rolled_back') {
            return response()->json([
                'transactionId' => $data['transactionId'],
                'transactionTime' => gmdate('Y-m-d\TH:i:s\Z'),
            ]);
        }

        $original_bet_amount = $bet->bet_amount;
        $settled_win_amount = $bet->win_amount;

        $oldInfo = json_decode($bet->info, true);
        $oldResultType = $oldInfo['resultType'] ?? 'unsettled';

        if (in_array($oldResultType, ['won', 'refund', 'cashout'])) {
            $user->balance -= $settled_win_amount;
        } elseif ($oldResultType === 'rollback') {
            $user->balance -= $original_bet_amount;
        }

        $user->balance += $original_bet_amount;
        $bet->settle_status = 'rolled_back';
        $bet->win_amount = 0;
        $oldInfo['resultType'] = 'rollback';
        $bet->info = json_encode($oldInfo);

        $bet->save();
        $user->save();

        return response()->json([
            'transactionId' => $data['transactionId'],
            'transactionTime' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }
}
