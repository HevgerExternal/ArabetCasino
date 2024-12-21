<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Turbostars\SignatureValidator;
use Illuminate\Support\Facades\Response;

class TurbostarsCallbackController extends Controller
{
    private $signatureValidator;

    public function __construct(SignatureValidator $signatureValidator)
    {
        $this->signatureValidator = $signatureValidator;
    }

    public function handleCallback(Request $request)
    {
        $endpoint = $request->path();
        $method = strtolower($request->method());
        $signature = $request->header('x-sign-jws');
    
        if (!$signature) {
            return response()->json([
                'status' => 'fail',
                'error' => 'Missing or invalid signature',
                'code' => 1,
            ], 400);
        }
    
        if (!$this->signatureValidator->validate($signature, $request->getContent())) {
            return response()->json([
                'status' => 'fail',
                'error' => 'Invalid signature'
            ], 403);
        }
    
        $callbacks = [
            'api/sportsbook/callback/user/profile' => ['service' => 'user', 'method' => 'profile'],
            'api/sportsbook/callback/user/balance' => ['service' => 'user', 'method' => 'balance'],
            'api/sportsbook/callback/payment/bet'  => ['service' => 'bet',  'method' => $method],
        ];
    
        if (!isset($callbacks[$endpoint])) {
            return response()->json(['status' => 'fail', 'error' => 'Invalid endpoint'], 404);
        }
    
        $serviceConfig = $callbacks[$endpoint];
        return $this->dispatchToService($serviceConfig['service'], $serviceConfig['method'], $request);
    }
    


    private function dispatchToService($serviceType, $method, $request)
    {
        switch ($serviceType) {
            case 'user':
                $service = resolve('App\Services\Turbostars\TurbostarsUserService');
                return $service->handle($method, $request);
            case 'bet':
                $service = resolve('App\Services\Turbostars\TurbostarsBetService');
                return $service->handle($method, $request);
            default:
                return response()->json(['status' => 'fail', 'error' => 'Service not found'], 500);
        }
    }
}
