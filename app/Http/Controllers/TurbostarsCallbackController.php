<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Laravel\Sanctum\PersonalAccessToken;

class TurbostarsCallbackController extends Controller
{
    private $partnerSecret;

    public function __construct()
    {
        // Retrieve the secret from the services configuration
        $this->partnerSecret = config('services.turbostars.partner_secret');
    }

    /**
     * Handle the generic callback for Sportsbook.
     */
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
        ];

        if (!array_key_exists($endpoint, $callbacks)) {
            Log::warning('Unhandled callback endpoint:', ['endpoint' => $endpoint]);
            return response()->json(['status' => 'fail', 'error' => 'Invalid endpoint'], 404);
        }

        return $this->{$callbacks[$endpoint]}($request);
    }

    /**
     * Validate the request payload.
     */
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

    /**
     * Verify the request signature.
     */
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

    /**
     * Handle the /user/profile request.
     */
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

    /**
     * Handle the /user/balance request.
     */
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

    /**
     * Retrieve user from token.
     */
    private function getUserFromToken(string $token)
    {
        $accessToken = PersonalAccessToken::findToken($token);

        return $accessToken ? $accessToken->tokenable : null;
    }
}
