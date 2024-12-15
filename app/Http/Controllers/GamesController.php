<?php

namespace App\Http\Controllers;

use App\Models\ExternalProvider;
use App\Services\LvlGameApiService;
use Illuminate\Http\Request;
use App\DTOs\ProviderDto;
use App\DTOs\GameDto;
use App\Models\Provider;

class GamesController extends Controller
{
    protected $lvlGameApiService;

    public function __construct(LvlGameApiService $lvlGameApiService)
    {
        $this->lvlGameApiService = $lvlGameApiService;
    }

    /**
     * Get game providers and wrap them in DTOs.
     */
    public function getProviders(Request $request)
    {
        try {
            // Get the type filter from the request, default to 'slot'
            $type = $request->query('type', 'slot');

            // Get providers from the API service
            $providers = $this->lvlGameApiService->getProviders();

            // Map providers to DTOs with type filter
            $providerDtos = [];
            foreach ($providers as $providerSlug) {
                $provider = Provider::where('slug', $providerSlug)
                    ->where('type', $type) // Apply the type filter
                    ->first();

                if ($provider) {
                    $providerDtos[] = new ProviderDto(
                        $provider->slug,
                        $provider->name,
                        $provider->image,
                        $provider->external_provider_id,
                        $provider->type
                    );
                }
            }

            // Transform DTOs into array format for response
            $response = array_map(fn($dto) => $dto->toArray(), $providerDtos);

            return response()->json([
                'status' => 'success',
                'data' => $response,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch providers.',
            ], 500);
        }
    }

    /**
     * Get games, optionally filtered by provider.
     */
    public function getGames(Request $request)
    {
        try {
            // Get the provider slug from the request
            $providerSlug = $request->query('provider');

            // Fetch games from the external API
            $games = $this->lvlGameApiService->getGames();
            $lvlSlots = ExternalProvider::where('name', 'LvlSlots')->first();

            $groupedGames = [];

            // Filter games by provider if specified
            if ($providerSlug) {
                if (!isset($games[$providerSlug])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Provider not found.',
                    ], 404);
                }
                $games = [$providerSlug => $games[$providerSlug]];
            }

            // Group games by provider name
            foreach ($games as $provider => $providerGames) {
                $providerGamesDtos = [];
                foreach ($providerGames as $game) {
                    $providerGamesDtos[] = (new GameDto(
                        $game['id'] ?? '',
                        $game['name'] ?? '',
                        $game['img'] ?? '',
                        $game['providerId'] ?? $lvlSlots->id
                    ))->toArray();
                }
                $groupedGames[$provider] = $providerGamesDtos;
            }

            return response()->json([
                'status' => 'success',
                'data' => $groupedGames,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error fetching games:', ['exception' => $e]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch games.',
            ], 500);
        }
    }


    public function openGame(Request $request)
    {
        try {
            $validated = $request->validate([
                'gameId' => 'required|integer',
                'providerId' => 'required|integer',
            ]);

            $gameId = $validated['gameId'];
            $providerId = $validated['providerId'];

            // Fetch the provider by ID
            $provider = Provider::findOrFail($providerId);

            // Ensure the provider is associated with LvlSlots
            if ($provider->externalProvider->name !== 'LvlSlots') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This provider is not supported for game opening.',
                ], 400);
            }

            // Prepare the payload for opening the game
            $payload = [
                'hall' => env('LVL_GAME_HALL'),
                'key' => env('LVL_GAME_KEY'),
                'cdnUrl' => '',
                'img' => 'game_img_2',
                'login' => $request->user()->username,
                'gameId' => $gameId,
                'domain' => config('app.url'),
                'exitUrl' => '',
                'demo' => '0',
                'language' => 'en',
            ];

            // Call the service to open the game
            $response = $this->lvlGameApiService->openGame($payload);

            // Extract the game URL if the response is successful
            if (isset($response['status']) && $response['status'] === 'success') {
                $gameUrl = $response['content']['game']['url'] ?? null;

                if ($gameUrl) {
                    return response()->json([
                        'status' => 'success',
                        'gameUrl' => $gameUrl,
                    ], 200);
                }
            }

            // Handle error case
            return response()->json([
                'status' => 'error',
                'message' => $response['message'] ?? 'Failed to open game.',
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Error opening game', ['exception' => $e]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to open game.',
            ], 500);
        }
    }

}
