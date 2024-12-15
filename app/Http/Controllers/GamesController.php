<?php

namespace App\Http\Controllers;

use App\Services\LvlGameApiService;
use Illuminate\Http\Request;
use App\DTOs\ProviderDto;
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

            // Filter games by provider if specified
            if ($providerSlug) {
                if (isset($games[$providerSlug])) {
                    $games = [$providerSlug => $games[$providerSlug]];
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Provider not found.',
                    ], 404);
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $games,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error fetching games:', ['exception' => $e]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch games.',
            ], 500);
        }
    }
}
