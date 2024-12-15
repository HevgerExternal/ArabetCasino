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
            // Get providers from the API service
            $providers = $this->lvlGameApiService->getProviders();
    
            // Map providers to DTOs
            $providerDtos = [];
            foreach ($providers as $providerSlug) {
                $provider = Provider::where('slug', $providerSlug)->first();
                
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
}
