<?php

namespace App\Http\Controllers;

use App\Models\ExternalProvider;
use App\Services\LvlGameApiService;
use App\Services\NexusGameApiService;
use Illuminate\Http\Request;
use App\DTOs\ProviderDto;
use App\DTOs\GameDto;
use App\Models\Provider;

class GamesController extends Controller
{
    protected $lvlGameApiService;
    protected $nexusGameApiService;

    public function __construct(LvlGameApiService $lvlGameApiService, NexusGameApiService $nexusGameApiService)
    {
        $this->lvlGameApiService = $lvlGameApiService;
        $this->nexusGameApiService = $nexusGameApiService;
    }

   /**
     * Get game providers and wrap them in DTOs.
     */
    public function getProviders(Request $request)
    {
        try {
            // Get the type filter from the request, default to 'slot'
            $type = $request->query('type', 'slot');

            $providerDtos = [];
            $processedSlugs = [];

            $externalProviders = ExternalProvider::all();

            foreach ($externalProviders as $externalProvider) {
                $providers = $this->fetchProvidersByExternalProvider($externalProvider, $type);

                foreach ($providers as $provider) {
                    if (!in_array($provider->slug, $processedSlugs)) {
                        $providerDtos[] = new ProviderDto(
                            $provider->slug,
                            $provider->name,
                            $provider->image,
                            $provider->external_provider_id,
                            $provider->type
                        );
                        $processedSlugs[] = $provider->slug;
                    }
                }
            }

            $response = array_map(fn($dto) => $dto->toArray(), $providerDtos);

            return response()->json([
                'status' => 'success',
                'data' => $response,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error fetching providers:', ['exception' => $e]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch providers.',
            ], 500);
        }
    }

    /**
     * Fetch providers for a specific external provider.
     *
     * @param ExternalProvider $externalProvider
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function fetchProvidersByExternalProvider(ExternalProvider $externalProvider, string $type)
    {
        if ($externalProvider->name === 'LvlSlots') {
            // Fetch providers from LvlGameApiService
            $lvlProviders = $this->lvlGameApiService->getProviders();

            return Provider::whereIn('slug', $lvlProviders)
                ->where('type', $type)
                ->where('external_provider_id', $externalProvider->id)
                ->get();
        }

        if ($externalProvider->name === 'Nexus') {
            // Fetch providers from NexusGameApiService
            $nexusProvidersResponse = $this->nexusGameApiService->getProviders();

            if (!is_array($nexusProvidersResponse)) {
                return collect();
            }

            $validNexusProviders = array_filter($nexusProvidersResponse, function ($provider) use ($type) {
                return $provider['type'] === $type && $provider['status'] === 1;
            });

            $slugs = array_map(fn($provider) => strtolower($provider['code']), $validNexusProviders);

            return Provider::whereIn('slug', $slugs)
                ->where('type', $type)
                ->where('external_provider_id', $externalProvider->id)
                ->get();
        }

        return collect();
    }

   /**
     * Get games, optionally filtered by provider and type.
     */
    public function getGames(Request $request)
    {
        try {
            $type = $request->query('type', 'slot');
            
            $providerSlug = $request->query('provider');

            $groupedGames = [];

            $externalProviders = ExternalProvider::all();

            foreach ($externalProviders as $externalProvider) {
                if ($externalProvider->name === 'LvlSlots') {
                    $lvlSlotsProviders = Provider::where('external_provider_id', $externalProvider->id)
                        ->where('type', $type)
                        ->pluck('slug')
                        ->toArray();

                    $lvlGames = $this->lvlGameApiService->getGames();

                    if ($providerSlug) {
                        if (in_array($providerSlug, $lvlSlotsProviders)) {
                            $groupedGames[$providerSlug] = $this->filterGames($lvlGames, $providerSlug, $externalProvider->id);
                        }
                    } else {
                        foreach ($lvlSlotsProviders as $slug) {
                            if (isset($lvlGames[$slug])) {
                                $groupedGames[$slug] = $this->filterGames($lvlGames, $slug, $externalProvider->id);
                            }
                        }
                    }
                }

                if ($externalProvider->name === 'Nexus') {
                    $nexusProviders = Provider::where('external_provider_id', $externalProvider->id)
                        ->where('type', $type)
                        ->pluck('slug')
                        ->toArray();
                    
                    foreach ($nexusProviders as $nexusProviderSlug) {
                        if (!$providerSlug || $providerSlug === $nexusProviderSlug) {
                            $nexusGames = $this->nexusGameApiService->getGames($nexusProviderSlug);
                         
                            $groupedGames[$nexusProviderSlug] = array_map(
                                fn($game) => (new GameDto(
                                    $game['game_code'] ?? '',
                                    ucwords(str_replace('_', ' ', strtolower($game['game_name']))) ?? '',
                                    $game['banner'] ?? '',
                                    $externalProvider->id,
                                    $nexusProviderSlug
                                ))->toArray(),
                                $nexusGames
                            );
                        }
                    }
                }
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

    /**
     * Filter games by provider and map to DTOs.
     *
     * @param array $games
     * @param string $providerSlug
     * @param int $externalProviderId
     * @return array
     */
    private function filterGames(array $games, string $providerSlug, int $externalProviderId): array
    {
        if (!isset($games[$providerSlug])) {
            return [];
        }

        return array_map(
            fn($game) => (new GameDto(
                $game['id'] ?? '',
                $game['name'] ?? '',
                $game['img'] ?? '',
                $externalProviderId,
                $providerSlug
            ))->toArray(),
            $games[$providerSlug]
        );
    }

    public function openGame(Request $request)
    {
        try {
            $validated = $request->validate([
                'gameId' => 'required|string',
                'externalProviderId' => 'required|integer',
                'provider' => 'nullable|string',
            ]);

            $externalProvider = ExternalProvider::findOrFail($validated['externalProviderId']);

            switch ($externalProvider->name) {
                case 'LvlSlots':
                    $response = $this->lvlGameApiService->openGame([
                        'gameId' => $validated['gameId'],
                        'login' => $request->user()->username,
                    ]);                    
                    break;

                case 'Nexus':
                    $response = $this->nexusGameApiService->openGame(
                        $request->user()->username,
                        $validated['provider'],
                        $validated['gameId'],
                    );
                    break;

                default:
                    return response()->json([
                        'status' => 'error',
                        'message' => 'This external provider is not supported for game opening.',
                    ], 400);
            }

            if (isset($response['status']) && $response['status'] === 'success') {
                return response()->json([
                    'status' => 'success',
                    'gameUrl' => $response['gameUrl'],
                ], 200);
            }

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
