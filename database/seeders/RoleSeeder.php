<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            'Root' => null,
            'Admin' => 'Root',
            'Manager' => 'Admin',
            'City Manager' => 'Manager',
            'Super Agent' => 'City Manager',
            'Agent' => 'Super Agent',
            'Player' => 'Agent',
        ];

        foreach ($roles as $role => $parent) {
            if (!Role::where('name', $role)->exists()) {
                Role::create([
                    'name' => $role,
                    'parent_id' => $parent ? Role::where('name', $parent)->first()->id : null,
                ]);
            }
        }
    }
}
