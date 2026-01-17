<?php

namespace Database\Seeders;

use App\Models\Nation;
use Illuminate\Database\Seeder;

class NationSeeder extends Seeder
{
    public function run(): void
    {
        $nations = require database_path('seeders/data/nations.php');

        foreach ($nations as $nation) {
            Nation::create($nation);
        }
    }
}
