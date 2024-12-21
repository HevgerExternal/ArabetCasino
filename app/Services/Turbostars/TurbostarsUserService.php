<?php

namespace App\Services\Turbostars;

use Illuminate\Http\Request;

class TurbostarsUserService extends TurbostarsBaseService
{
    public function handle($method, Request $request)
    {
        switch ($method) {
            case 'profile':
                return $this->profile($request);
            case 'balance':
                return $this->balance($request);
            default:
                return response()->json(['status' => 'fail', 'error' => 'Method not supported'], 405);
        }
    }

    protected function profile(Request $request)
    {
        $payload = $this->validateRequest($request, [
            'token' => 'required|string|min:10|max:64',
            'requestId' => 'required|string|min:10|max:64',
        ]);

        if ($payload instanceof \Illuminate\Http\JsonResponse) {
            return $payload;
        }

        $user = $this->getUserFromToken($payload['token']);
        if (!$user) {
            return response()->json([
                'status' => 'fail',
                'error' => 'Invalid token or user not found',
                'code' => 3
            ], 400);
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

    protected function balance(Request $request)
    {
        $payload = $this->validateRequest($request, [
            'token' => 'required|string|min:10|max:64',
            'userId' => 'required|string|min:1|max:64',
            'currency' => 'required|string|min:3|max:5',
            'requestId' => 'required|string|min:10|max:64',
        ]);

        if ($payload instanceof \Illuminate\Http\JsonResponse) {
            return $payload;
        }

        $user = $this->getUserFromToken($payload['token']);

        if (!$user || (string) $user->id !== $payload['userId']) {
            return response()->json([
                'status' => 'fail',
                'error' => 'Invalid token or user mismatch',
                'code' => 3
            ], 400);
        }

        $userBalance = [
            'userId' => (string) $user->id,
            'currency' => $payload['currency'],
            'amount' => $user->balance,
        ];

        return response()->json($userBalance, 200);
    }
}
