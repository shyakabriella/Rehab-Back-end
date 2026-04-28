<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->first();

        if (!$adminRole) {
            $this->command->error('Admin role not found. Please run RoleSeeder first.');
            return;
        }

        User::updateOrCreate(
            ['email' => 'admin@azultech.rw'],
            [
                'role_id'        => $adminRole->id,
                'name'           => 'System Admin',
                'email'          => 'admin@azultech.rw',
                'phone'          => '0780000000',
                'password'       => 'password123',
                'status'         => 'active',
                'is_anonymous'   => false,
                'anonymous_name' => null,
            ]
        );
    }
}