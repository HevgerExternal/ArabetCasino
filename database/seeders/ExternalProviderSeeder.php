<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExternalProvider;

class ExternalProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $providers = [
            'LvlSlots',
            'Nexus',
        ];

        foreach ($providers as $providerName) {
            ExternalProvider::firstOrCreate(['name' => $providerName]);
        }
    }
}
