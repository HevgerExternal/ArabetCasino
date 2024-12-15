<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Provider;
use App\Models\ExternalProvider;

class ProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Base URL for provider images
        $baseImageUrl = config('app.url') . '/providers';

        // Predefined list of providers
        $providers = [
            [
                'slug' => 'ainsworth',
                'name' => 'Ainsworth',
                'external_provider_name' => 'LvlSlots',
            ],
            [
                'slug' => 'pragmatic',
                'name' => 'Pragmatic Play',
                'external_provider_name' => 'Nexus',
            ],
        ];

        foreach ($providers as $provider) {
            // Find or create the external provider
            $externalProvider = ExternalProvider::firstOrCreate(
                ['name' => $provider['external_provider_name']],
                []
            );

            // Construct the image URL based on the slug
            $imageUrl = "{$baseImageUrl}/{$provider['slug']}.png";

            // Update or create the provider
            Provider::updateOrCreate(
                ['slug' => $provider['slug']],
                [
                    'name' => $provider['name'],
                    'image' => $imageUrl,
                    'external_provider_id' => $externalProvider->id,
                ]
            );
        }
    }
}