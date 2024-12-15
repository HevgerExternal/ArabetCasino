<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LvlGameApiService
{
    protected string $apiUrl = 'https://tbs2api.aslot.net/API/';

    /**
     * Send a request to the external API.
     *
     * @param string $cmd
     * @param array $additionalPayload
     * @return array
     */
    public function sendRequest(string $cmd, array $additionalPayload = []): array
    {
        try {
            $payload = array_merge([
                'hall' => env('LVL_GAME_HALL'),
                'key' => env('LVL_GAME_KEY'),
                'cmd' => $cmd,
                'cdnUrl' => '',
                'img' => 'game_img_2',
            ], $additionalPayload);

            $jsonPayload = json_encode($payload);

            $response = Http::withBody($jsonPayload, 'application/json')->get($this->apiUrl);

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

    /**
     * Get only the provider keys from the response.
     *
     * @return array
     */
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
}
