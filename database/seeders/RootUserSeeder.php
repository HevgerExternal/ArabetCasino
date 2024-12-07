<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\UserHierarchy;
use Illuminate\Support\Facades\Hash;

class RootUserSeeder extends Seeder
{
    public function run(): void
    {
        // Get the Root role
        $rootRole = Role::where('name', 'Root')->first();

        // Create the Root user
        $rootUser = User::create([
            'username' => 'root',
            'password' => Hash::make('root'),
            'roleId' => $rootRole->id,
            'balance' => 0,
            'status' => true,
            'last_accessed' => now(),
        ]);

        // Add hierarchy entry for the Root user (self-reference with depth 0)
        UserHierarchy::create([
            'ancestorId' => $rootUser->id,
            'descendantId' => $rootUser->id,
            'depth' => 0,
        ]);
    }
}
