<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class TVClubRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Add only the missing role. Preserve existing IDs and custom descriptions.
        Role::firstOrCreate(
            ['name' => User::ROLE_TVCLUB],
            ['description' => 'Pengelola Program Acara dan Portal TV Club.'],
        );
    }
}
