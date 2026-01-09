<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UpdateBenjaminPasswordSeeder extends Seeder
{
    public function run(): void
    {
        User::query()
            ->where('email', 'benjaminzavala74@gmail.com')
            ->update(['password' => Hash::make('agodos93')]);
    }
}
