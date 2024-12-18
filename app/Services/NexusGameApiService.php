<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NexusGameApiService
{
    protected string $apiUrl = 'https://api.shinoapi.com';
    protected string $agentCode = 'Arabet';
    protected string $agentToken = '4e77f1ff8d063fefd57a274e6a08eb6d';

    /**
     * Send a request to the Nexus API.
     *
     * @param array $payload
     * @return array
     */
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

    /**
     * Get the list of providers.
     *
     * @return array
     */
    public function getProviders(): array
    {
        $payload = [
            'method' => 'provider_list',
            'agent_code' => $this->agentCode,
            'agent_token' => $this->agentToken,
        ];

        $response = $this->sendRequest($payload);

        if ($response['status'] === 1) {
            $providers = $response['providers'] ?? [];
            return $providers;
        }

        return [
            'status' => 'error',
            'message' => $response['message'] ?? 'Failed to retrieve providers.',
        ];
    }

    /**
     * Get the list of games for a specific provider.
     *
     * @param string $providerCode
     * @return array
     */
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
            $games = $response['games'] ?? [];
            return $games;
        }

        return [
            'status' => 'error',
            'message' => $response['message'] ?? 'Failed to retrieve games.',
        ];
    }

    /**
     * Launch a specific game.
     *
     * @param string $userCode
     * @param string $providerCode
     * @param string $gameCode
     * @param string $language
     * @return array
     */
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

        if ($response['status'] === 1 && $response['msg'] === "SUCCESS") {
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