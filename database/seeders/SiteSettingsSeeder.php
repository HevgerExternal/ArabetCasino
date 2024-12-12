<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SiteSettings;

class SiteSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default site settings if not exists
        if (!SiteSettings::exists()) {
            SiteSettings::create([
                'currency' => 'LBP',
            ]);
        }
    }
}