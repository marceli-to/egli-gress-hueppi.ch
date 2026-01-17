<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = require database_path('seeders/data/locations.php');

        foreach ($locations as $location) {
            Location::create($location);
        }
    }
}
