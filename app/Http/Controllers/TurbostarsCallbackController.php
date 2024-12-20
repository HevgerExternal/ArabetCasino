<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\User;
use App\Models\SportsTicket;

class TurbostarsCallbackController extends Controller
{
    private $partnerSecret;

    public function __construct()
    {
        $this->partnerSecret = config('services.turbostars.partner_secret');
    }

    public function handleCallback(Request $request)
    {
        $endpoint = $request->path();
        $signature = $request->header('x-sign-jws');

        if (!$this->isValidSignature($signature, $request->getContent())) {
            return response()->json([
                'status' => 'fail',
                'error' => 'Invalid signature',
            ], 403);
        }

        $callbacks = [
            'api/sportsbook/callback/user/profile' => 'handleUserProfile',
            'api/sportsbook/callback/user/balance' => 'handleUserBalance',
            'api/sportsbook/callback/payment/bet' => 'handleBetOperations',
        ];

        if (!array_key_exists($endpoint, $callbacks)) {
            Log::warning('Unhandled callback endpoint:', ['endpoint' => $endpoint]);
            return response()->json(['status' => 'fail', 'error' => 'Invalid endpoint'], 404);
        }

        return $this->{$callbacks[$endpoint]}($request);
    }

    private function isValidSignature(string $sign, string $body): bool
    {
        try {
            $signParts = explode('.', $sign);

            JWT::decode(
                implode('.', [
                    $signParts[0],
                    JWT::urlsafeB64Encode($body),
                    $signParts[2],
                ]),
                new Key($this->partnerSecret, 'HS256')
            );

            return true;
        } catch (\Throwable $error) {
            Log::error('Signature verification failed:', ['error' => $error->getMessage()]);
            return false;
        }
    }

    private function handleUserProfile(Request $request)
    {
        $payload = $this->validateRequest($request, [
            'token' => 'required|string|min:10|max:64',
            'requestId' => 'required|string|min:10|max:64',
        ]);

        if (is_array($payload) && isset($payload['error'])) {
            return $payload;
        }

        $user = $this->getUserFromToken($payload['token']);

        if (!$user) {
            return response()->json(['status' => 'fail', 'error' => 'Invalid token or user not found'], 403);
        }

        $userProfile = [
            'userId' => (string) $user->id,
            'currency' => 'LBP',
            'currencies' => ['LBP', 'USD'],
            'isTest' => false,
            'customFields' => [
                'nickname' => $user->username ?? 'Guest',
                'userSessionIp' => $request->ip(),
                'registrationDate' => $user->created_at->toDateTimeString(),
            ],
        ];

        return response()->json($userProfile, 200);
    }

    private function handleUserBalance(Request $request)
    {
        $payload = $this->validateRequest($request, [
            'token' => 'required|string|min:10|max:64',
            'userId' => 'required|string|min:1|max:64',
            'currency' => 'required|string|min:3|max:5',
            'requestId' => 'required|string|min:10|max:64',
        ]);

        if (is_array($payload) && isset($payload['error'])) {
            return $payload;
        }

        $user = $this->getUserFromToken($payload['token']);

        if (!$user || (string) $user->id !== $payload['userId']) {
            return response()->json(['status' => 'fail', 'error' => 'Invalid token or user mismatch'], 404);
        }

        $userBalance = [
            'userId' => (string) $user->id,
            'currency' => $payload['currency'],
            'amount' => $user->balance,
        ];

        return response()->json($userBalance, 200);
    }

    private function handleBetOperations(Request $request)
    {
        $type = $request->input('type');

        switch ($type) {
            case 1:
                return $this->placeBet($request);
            case 2:
                return $this->settleBet($request);
            case 3:
                return $this->rollbackBet($request);
            default:
                return response()->json(['status' => 'fail', 'error' => 'Invalid type'], 400);
        }
    }

    private function placeBet(Request $request)
    {
        $data = $this->validateRequest($request, [
            'token' => 'required|string',
            'transactionId' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string',
            'userId' => 'required|string',
            'type' => 'required|integer|in:1',
        ]);

        if (is_array($data) && isset($data['error'])) {
            return $data;
        }

        $user = User::find($data['userId']);

        if (!$user) {
            return response()->json(['code' => 404, 'message' => 'User not found'], 404);
        }

        if ($user->balance < $data['amount']) {
            return response()->json(['code' => 7, 'message' => 'Not enough money'], 400);
        }

        $user->balance -= $data['amount'];
        $user->save();

        SportsTicket::create([
            'user_id' => $user->id,
            'transaction_id' => $data['transactionId'],
            'amount' => $data['amount'],
            'win_amount' => 0,
            'currency' => $data['currency'],
            'type' => 'bet',
            'game_type' => 'sportsbook',
            'metadata' => $data,
            'settle_status' => 'unsettled',
        ]);

        return response()->json([
            'transactionId' => $data['transactionId'],
            'transactionTime' => now()->toIso8601String(),
        ]);
    }

    private function settleBet(Request $request)
    {
        $data = $this->validateRequest($request, [
            'transactionId' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|min:3|max:5',
            'type' => 'required|integer|in:2',
            'userId' => 'required|string',
            'resultType' => 'required|string|in:won,lost,refund,cashout',
        ]);

        if (is_array($data) && isset($data['error'])) {
            return $data;
        }

        $ticket = SportsTicket::where('transaction_id', $data['transactionId'])->first();

        if (!$ticket) {
            return response()->json(['code' => 404, 'message' => 'Ticket not found'], 404);
        }

        if ($ticket->settle_status === 'settled') {
            return response()->json(['code' => 400, 'message' => 'Ticket already settled'], 400);
        }

        $user = User::find($data['userId']);

        if (!$user) {
            return response()->json(['code' => 404, 'message' => 'User not found'], 404);
        }

        if ($data['amount'] > 0) {
            $user->balance += $data['amount'];
            $ticket->win_amount = $data['amount'];
        }

        $ticket->settle_status = 'settled';
        $ticket->save();
        $user->save();

        return response()->json([
            'transactionId' => $data['transactionId'],
            'transactionTime' => now()->toIso8601String(),
        ]);
    }

    private function rollbackBet(Request $request)
    {
        $data = $this->validateRequest($request, [
            'transactionId' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|min:3|max:5',
            'type' => 'required|integer|in:3',
            'userId' => 'required|string',
        ]);

        if (is_array($data) && isset($data['error'])) {
            return $data;
        }

        $ticket = SportsTicket::where('transaction_id', $data['transactionId'])->first();

        if (!$ticket) {
            return response()->json(['code' => 404, 'message' => 'Ticket not found'], 404);
        }

        if ($ticket->settle_status === 'rolled_back') {
            return response()->json(['code' => 400, 'message' => 'Ticket already rolled back'], 400);
        }

        $user = User::find($data['userId']);

        if (!$user) {
            return response()->json(['code' => 404, 'message' => 'User not found'], 404);
        }

        $user->balance += $ticket->amount;
        $ticket->settle_status = 'rolled_back';
        $ticket->save();
        $user->save();

        return response()->json([
            'transactionId' => $data['transactionId'],
            'transactionTime' => now()->toIso8601String(),
        ]);
    }

    private function getUserFromToken(string $token)
    {
        $accessToken = PersonalAccessToken::findToken($token);

        return $accessToken ? $accessToken->tokenable : null;
    }

    private function validateRequest(Request $request, array $rules)
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'fail',
                'error' => 'Invalid request payload',
                'details' => $validator->errors(),
            ], 400);
        }

        return $validator->validated();
    }
}
