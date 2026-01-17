<?php

/**
 * Teams for World Cup 2022 with group assignments
 *
 * nation_code references nations.code
 *
 * Usage in seeder:
 * $teams = require database_path('seeders/data/teams.php');
 * foreach ($teams as $team) {
 *     Team::create([
 *         'tournament_id' => $tournament->id,
 *         'nation_id' => Nation::where('code', $team['nation_code'])->first()->id,
 *         'group_name' => $team['group'],
 *     ]);
 * }
 */

return [
    // Group A
    ['nation_code' => 'qa', 'group' => 'A', 'position' => 1],
    ['nation_code' => 'ec', 'group' => 'A', 'position' => 2],
    ['nation_code' => 'sn', 'group' => 'A', 'position' => 3],
    ['nation_code' => 'nl', 'group' => 'A', 'position' => 4],

    // Group B
    ['nation_code' => 'gb-eng', 'group' => 'B', 'position' => 1],
    ['nation_code' => 'ir', 'group' => 'B', 'position' => 2],
    ['nation_code' => 'us', 'group' => 'B', 'position' => 3],
    ['nation_code' => 'gb-wls', 'group' => 'B', 'position' => 4],

    // Group C
    ['nation_code' => 'ar', 'group' => 'C', 'position' => 1],
    ['nation_code' => 'sa', 'group' => 'C', 'position' => 2],
    ['nation_code' => 'mx', 'group' => 'C', 'position' => 3],
    ['nation_code' => 'pl', 'group' => 'C', 'position' => 4],

    // Group D
    ['nation_code' => 'fr', 'group' => 'D', 'position' => 1],
    ['nation_code' => 'au', 'group' => 'D', 'position' => 2],
    ['nation_code' => 'dk', 'group' => 'D', 'position' => 3],
    ['nation_code' => 'tn', 'group' => 'D', 'position' => 4],

    // Group E
    ['nation_code' => 'es', 'group' => 'E', 'position' => 1],
    ['nation_code' => 'cr', 'group' => 'E', 'position' => 2],
    ['nation_code' => 'de', 'group' => 'E', 'position' => 3],
    ['nation_code' => 'jp', 'group' => 'E', 'position' => 4],

    // Group F
    ['nation_code' => 'be', 'group' => 'F', 'position' => 1],
    ['nation_code' => 'ca', 'group' => 'F', 'position' => 2],
    ['nation_code' => 'ma', 'group' => 'F', 'position' => 3],
    ['nation_code' => 'hr', 'group' => 'F', 'position' => 4],

    // Group G
    ['nation_code' => 'br', 'group' => 'G', 'position' => 1],
    ['nation_code' => 'rs', 'group' => 'G', 'position' => 2],
    ['nation_code' => 'ch', 'group' => 'G', 'position' => 3],
    ['nation_code' => 'cm', 'group' => 'G', 'position' => 4],

    // Group H
    ['nation_code' => 'pt', 'group' => 'H', 'position' => 1],
    ['nation_code' => 'gh', 'group' => 'H', 'position' => 2],
    ['nation_code' => 'uy', 'group' => 'H', 'position' => 3],
    ['nation_code' => 'kr', 'group' => 'H', 'position' => 4],
];
