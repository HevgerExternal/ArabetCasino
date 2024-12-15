<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckTokenType
{
    public function handle(Request $request, Closure $next, $type)
    {
        $token = $request->user()->currentAccessToken();

        // Check if abilities are defined and contain the required type
        if (!$token || !isset($token->abilities) || !in_array($type, $token->abilities)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        return $next($request);
    }
}
