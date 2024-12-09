<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Database\Seeder;

class UserSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fetch all users who don't have settings
        $usersWithoutSettings = User::doesntHave('settings')->get();

        foreach ($usersWithoutSettings as $user) {
            UserSettings::create([
                'user_id' => $user->id,
                'numberFormat' => 'Large',
                'useThousandSeparator' => true,
            ]);
        }
    }
}
