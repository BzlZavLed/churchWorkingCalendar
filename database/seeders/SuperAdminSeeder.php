<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'superadmin@church.local');
        $password = env('SUPERADMIN_PASSWORD', 'password');
        $name = env('SUPERADMIN_NAME', 'Super Admin');

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => User::ROLE_SUPERADMIN,
            ]
        );
    }
}
