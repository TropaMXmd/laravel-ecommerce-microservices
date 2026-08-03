<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(

            [
                'email' => 'admin@abc.com',
            ],

            [
                'name' => 'System Administrator',

                'password' => 'password',

                'email_verified_at' => now(),

                'is_active' => true,
            ]
        );

        $admin->syncRoles(['admin']);
    }
}