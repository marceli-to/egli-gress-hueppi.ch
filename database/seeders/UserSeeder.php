<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Marcel Stadelmann',
            'email' => 'm@marceli.to',
            'password' => Hash::make('7aq31rr23'),
            'is_admin' => true,
        ]);
    }
}
