<?php

/**
 * Special Tipp Specifications for World Cup 2022
 *
 * Types:
 * - WINNER: Predict tournament/group winner
 * - FINAL_RANKING: Predict final position of a team
 * - TOTAL_GOALS: Predict total goals scored
 *
 * Usage in seeder:
 * $specs = require database_path('seeders/data/special_tipp_specs.php');
 * foreach ($specs as $spec) {
 *     SpecialTippSpec::create([...]);
 * }
 */

return [
    // Tournament Champion (30 points)
    [
        'name' => 'WINNER_WORLDCUP',
        'label' => 'World Cup Champion',
        'type' => 'WINNER',
        'value' => 30,
        'team_code' => null, // User selects from all teams
    ],

    // Group Winners (3 points each)
    [
        'name' => 'WINNER_GROUP_A',
        'label' => 'Winner Group A',
        'type' => 'WINNER',
        'value' => 3,
        'group' => 'A',
    ],
    [
        'name' => 'WINNER_GROUP_B',
        'label' => 'Winner Group B',
        'type' => 'WINNER',
        'value' => 3,
        'group' => 'B',
    ],
    [
        'name' => 'WINNER_GROUP_C',
        'label' => 'Winner Group C',
        'type' => 'WINNER',
        'value' => 3,
        'group' => 'C',
    ],
    [
        'name' => 'WINNER_GROUP_D',
        'label' => 'Winner Group D',
        'type' => 'WINNER',
        'value' => 3,
        'group' => 'D',
    ],
    [
        'name' => 'WINNER_GROUP_E',
        'label' => 'Winner Group E',
        'type' => 'WINNER',
        'value' => 3,
        'group' => 'E',
    ],
    [
        'name' => 'WINNER_GROUP_F',
        'label' => 'Winner Group F',
        'type' => 'WINNER',
        'value' => 3,
        'group' => 'F',
    ],
    [
        'name' => 'WINNER_GROUP_G',
        'label' => 'Winner Group G',
        'type' => 'WINNER',
        'value' => 3,
        'group' => 'G',
    ],
    [
        'name' => 'WINNER_GROUP_H',
        'label' => 'Winner Group H',
        'type' => 'WINNER',
        'value' => 3,
        'group' => 'H',
    ],

    // Switzerland specific (for Swiss users - 6 points)
    [
        'name' => 'FINAL_RANKING_CH',
        'label' => 'Switzerland Final Ranking',
        'type' => 'FINAL_RANKING',
        'value' => 6,
        'team_code' => 'ch', // Fixed team
    ],
    [
        'name' => 'TOTAL_GOALS_CH',
        'label' => 'Switzerland Total Goals',
        'type' => 'TOTAL_GOALS',
        'value' => 6,
        'team_code' => 'ch', // Fixed team
    ],
];
