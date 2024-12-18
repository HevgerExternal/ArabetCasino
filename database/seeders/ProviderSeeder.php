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

        // List of provider slugs for LVL Slots
        $lvlSlotsProviders = ["rubyplay", "novomatic", "apollo", "amatic", "playngo", "scientific_games", "kajot", "pragmatic", "microgaming", "quickspin", "NetEnt", "habanero", "igt", "aristocrat", "igrosoft", "apex", "merkur", "egt", "roulette", "bingo", "keno"];            
        
        // Additional providers for Nexus
        $nexusProviders = [
            [
                'slug' => 'evolution',
                'name' => 'Evolution',
            ],
            [
                'slug' => 'pragmatic_live',
                'name' => 'Pragmatic Live',
            ]
        ];

        // Assign all LVL Slots providers to LvlSlots
        $lvlSlotsExternalProvider = ExternalProvider::firstOrCreate(['name' => 'LvlSlots'], []);
        foreach ($lvlSlotsProviders as $slug) {
            $name = ucwords(str_replace('_', ' ', $slug));
            $imageUrl = "{$baseImageUrl}/{$slug}.png";

            Provider::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'image' => $imageUrl,
                    'external_provider_id' => $lvlSlotsExternalProvider->id,
                    'type' => 'slot'
                ]
            );
        }

        // Assign additional Nexus providers to Nexus
        $nexusExternalProvider = ExternalProvider::firstOrCreate(['name' => 'Nexus'], []);
        foreach ($nexusProviders as $provider) {
            $slug = $provider['slug'];
            $name = $provider['name'];
            $imageUrl = "{$baseImageUrl}/{$slug}.png";

            Provider::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'image' => $imageUrl,
                    'external_provider_id' => $nexusExternalProvider->id,
                    'type' => 'live'
                ]
            );
        }
    }
}