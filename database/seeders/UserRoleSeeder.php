<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UserRole;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['admin', 'member'];
        
        foreach ($roles as $role) {
            UserRole::firstOrCreate(['role' => $role]);
        }
    }
}

