<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates a default admin user for testing/development.
     */
    public function run(): void
    {
        // Ensure admin role exists
        $adminRole = UserRole::firstOrCreate(['role' => 'admin']);

        // Create admin user if it doesn't exist
        User::firstOrCreate(
            ['email' => 'admin@homelife.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@homelife.com',
                'password' => Hash::make('admin123'), // Change this password in production!
                'user_role_id' => $adminRole->id,
            ]
        );

        $this->command->info('Admin user created: admin@homelife.com / admin123');
        $this->command->warn('⚠️  Please change the default password in production!');
    }
}

