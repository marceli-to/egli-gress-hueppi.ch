<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Seed nations and locations first (no dependencies)
        $this->call([
            NationSeeder::class,
            LocationSeeder::class,
        ]);

        // Seed tournament with teams, games, and special tipp specs
        $this->call(WorldCup2022Seeder::class);

        // Create users
        $this->call(UserSeeder::class);
    }
}
