<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LvlGameApiService
{
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiUrl = config('services.lvl.api_domain');
    }

    public function sendRequest(string $cmd, array $additionalPayload = []): array
    {
        try {
            $payload = array_merge([
                'hall' => config('services.lvl.game_hall'),
                'key' => config('services.lvl.game_key'),
                'cmd' => $cmd,
                'cdnUrl' => '',
                'img' => 'game_img_2',
            ], $additionalPayload);

            $jsonPayload = json_encode($payload);

            $response = Http::withBody($jsonPayload, 'application/json')->post($this->apiUrl);

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'status' => 'error',
                'message' => 'HTTP Error: ' . $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Error in Game API Service:', ['exception' => $e]);

            return [
                'status' => 'error',
                'message' => 'An exception occurred: ' . $e->getMessage(),
            ];
        }
    }

    public function getProviders(): array
    {
        $response = $this->sendRequest('getGamesList');

        if ($response['status'] === 'success') {
            $content = $response['content'] ?? [];
            return array_keys($content);
        }

        return [
            'status' => 'error',
            'message' => $response['message'] ?? 'Failed to retrieve providers.',
        ];
    }

    public function getGames(): array
    {
        $response = $this->sendRequest('getGamesList');

        if ($response['status'] === 'success') {
            $content = $response['content'] ?? [];
            return $content;
        }

        throw new \Exception('Failed to fetch games from the API: ' . ($response['message'] ?? 'Unknown error'));
    }

    public function openGame(array $data): array
    {
        $url = $this->apiUrl . 'openGame/';

        $payload = [
            'hall' => config('services.lvl.game_hall'),
            'key' => config('services.lvl.game_key'),
            'gameId' => $data['gameId'],
            'login' => $data['login'],
            'demo' => $data['demo'] ?? '0',
            'language' => $data['language'] ?? 'en',
            'cdnUrl' => $data['cdnUrl'] ?? '',
            'exitUrl' => $data['exitUrl'] ?? config('services.lvl.exit_url'),
        ];

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])->post($url, $payload);

            if ($response->successful() && isset($response['status']) && $response['status'] === 'success') {
                return [
                    'status' => 'success',
                    'gameUrl' => $response['content']['game']['url'] ?? null,
                ];
            }

            Log::error('Failed to open game:', ['response' => $response->json()]);

            return [
                'status' => 'error',
                'message' => $response->json('message') ?? 'Failed to open game.',
            ];
        } catch (\Exception $e) {
            Log::error('Error opening game:', ['exception' => $e]);

            return [
                'status' => 'error',
                'message' => 'An exception occurred: ' . $e->getMessage(),
            ];
        }
    }
}