<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NexusGameApiService
{
    protected string $apiUrl;
    protected string $agentCode;
    protected string $agentToken;

    public function __construct()
    {
        $this->apiUrl = config('services.nexus.api_url');
        $this->agentCode = config('services.nexus.agent_code');
        $this->agentToken = config('services.nexus.agent_token');
    }

    protected function sendRequest(array $payload): array
    {
        try {
            $response = Http::post($this->apiUrl, $payload);

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'status' => 'error',
                'message' => 'HTTP Error: ' . $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Error in Nexus API Service:', ['exception' => $e]);

            return [
                'status' => 'error',
                'message' => 'An exception occurred: ' . $e->getMessage(),
            ];
        }
    }

    public function getProviders(): array
    {
        $payload = [
            'method' => 'provider_list',
            'agent_code' => $this->agentCode,
            'agent_token' => $this->agentToken,
        ];

        $response = $this->sendRequest($payload);

        if ($response['status'] === 1) {
            return $response['providers'] ?? [];
        }

        return [
            'status' => 'error',
            'message' => $response['message'] ?? 'Failed to retrieve providers.',
        ];
    }

    public function getGames(string $providerCode): array
    {
        $payload = [
            'method' => 'game_list',
            'agent_code' => $this->agentCode,
            'agent_token' => $this->agentToken,
            'provider_code' => $providerCode,
        ];

        $response = $this->sendRequest($payload);

        if ($response['status'] === 1) {
            return $response['games'] ?? [];
        }

        return [
            'status' => 'error',
            'message' => $response['message'] ?? 'Failed to retrieve games.',
        ];
    }

    public function openGame(string $userCode, string $providerCode, string $gameCode, string $language = 'en'): array
    {
        $payload = [
            'method' => 'game_launch',
            'agent_code' => $this->agentCode,
            'agent_token' => $this->agentToken,
            'user_code' => $userCode,
            'provider_code' => strtoupper($providerCode),
            'game_code' => $gameCode,
            'lang' => $language,
        ];

        $response = $this->sendRequest($payload);

        if ($response['status'] === 1 && $response['msg'] === 'SUCCESS') {
            return [
                'status' => 'success',
                'gameUrl' => $response['launch_url'] ?? null,
            ];
        }

        return [
            'status' => 'error',
            'message' => $response['msg'] ?? 'Failed to launch game.',
        ];
    }
}
