<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Location;
use App\Models\Nation;
use App\Models\SpecialTippSpec;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Seeder;

class WorldCup2022Seeder extends Seeder
{
    public function run(): void
    {
        // Create tournament
        $tournament = Tournament::create([
            'name' => 'WM 2026',
            'slug' => 'wm-2026',
            'is_active' => true,
        ]);

        // Create teams
        $teams = require database_path('seeders/data/teams.php');
        $teamsByCode = [];

        foreach ($teams as $teamData) {
            $nation = Nation::where('code', $teamData['nation_code'])->first();
            $team = Team::create([
                'tournament_id' => $tournament->id,
                'nation_id' => $nation->id,
                'group_name' => $teamData['group'],
            ]);
            $teamsByCode[$teamData['group'] . $teamData['position']] = $team;
        }

        // Get locations by slug
        $locations = Location::all()->keyBy('slug');

        // Create games
        $games = require database_path('seeders/data/games.php');

        foreach ($games as $gameData) {
            $game = [
                'tournament_id' => $tournament->id,
                'game_type' => $gameData['type'],
                'kickoff_at' => $gameData['kickoff_at'],
                'location_id' => $locations[$gameData['location']]->id,
            ];

            if ($gameData['type'] === 'GROUP') {
                // Group stage game
                $game['group_name'] = $gameData['group'];
                $game['home_team_id'] = $teamsByCode[$gameData['home']]->id;
                $game['visitor_team_id'] = $teamsByCode[$gameData['visitor']]->id;
            } else {
                // Knockout stage game
                $game['home_team_placeholder'] = $gameData['home_placeholder'];
                $game['visitor_team_placeholder'] = $gameData['visitor_placeholder'];
            }

            Game::create($game);
        }

        // Create special tipp specs
        $specs = require database_path('seeders/data/special_tipp_specs.php');

        foreach ($specs as $specData) {
            SpecialTippSpec::create([
                'tournament_id' => $tournament->id,
                'name' => $specData['name'],
                'type' => $specData['type'],
                'value' => $specData['value'],
            ]);
        }
    }
}
