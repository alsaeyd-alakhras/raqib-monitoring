<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (RaqibMasterSeeder::$dryRun) {
            return;
        }

        $config = config('raqib.super_admin');

        User::updateOrCreate(
            ['username' => $config['username']],
            [
                'name' => $config['name'],
                'email' => $config['email'],
                'password' => Hash::make($config['password']),
                'user_type' => 'admin',
                'is_active' => true,
                'super_admin' => true,
                'last_activity' => now(),
            ]
        );
    }
}
