<?php

namespace App\Services\Turbostars;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class TurbostarsBaseService
{
    protected function getUserFromToken(string $token)
    {
        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            Log::error('Invalid token provided', ['token' => $token]);
            return null;
        }

        return $accessToken->tokenable;
    }

    protected function validateRequest(Request $request, array $rules)
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
