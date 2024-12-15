<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run the RoleSeeder first to create roles
        $this->call(RoleSeeder::class);

        // Run the UserSeeder next to create the root user
        $this->call(RootUserSeeder::class);

        // Run the UserSettingsSeeder to create settings if not created before
        $this->call(UserSettingsSeeder::class);

        // Run the SiteSettingsSeeder to set site settings
        $this->call(SiteSettingsSeeder::class);

        // Run the seeders for providers
        $this->call(ExternalProviderSeeder::class);
        $this->call(ProviderSeeder::class);
    }
}
