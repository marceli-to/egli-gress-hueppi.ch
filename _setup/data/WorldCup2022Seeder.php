<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Location;
use App\Models\Nation;
use App\Models\SpecialTippSpec;
use App\Models\Team;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Example Seeder for World Cup 2022 data
 *
 * Copy this file to database/seeders/WorldCup2022Seeder.php
 * Copy the data files to database/seeders/data/
 *
 * Run: php artisan db:seed --class=WorldCup2022Seeder
 */
class WorldCup2022Seeder extends Seeder
{
    public function run(): void
    {
        $this->seedNations();
        $this->seedLocations();
        $tournament = $this->seedTournament();
        $this->seedTeams($tournament);
        $this->seedGames($tournament);
        $this->seedSpecialTippSpecs($tournament);
    }

    private function seedNations(): void
    {
        $nations = require __DIR__ . '/data/nations.php';

        foreach ($nations as $nation) {
            Nation::updateOrCreate(
                ['code' => $nation['code']],
                $nation
            );
        }

        $this->command->info('Nations seeded: ' . count($nations));
    }

    private function seedLocations(): void
    {
        $locations = require __DIR__ . '/data/locations.php';

        foreach ($locations as $location) {
            Location::updateOrCreate(
                ['slug' => $location['slug']],
                $location
            );
        }

        $this->command->info('Locations seeded: ' . count($locations));
    }

    private function seedTournament(): Tournament
    {
        // Deactivate all existing tournaments
        Tournament::query()->update(['is_active' => false]);

        $tournament = Tournament::updateOrCreate(
            ['name' => 'World Cup 2022'],
            [
                'name' => 'World Cup 2022',
                'slug' => 'worldcup-2022',
                'is_active' => true,
            ]
        );

        $this->command->info('Tournament created: ' . $tournament->name);

        return $tournament;
    }

    private function seedTeams(Tournament $tournament): void
    {
        $teams = require __DIR__ . '/data/teams.php';

        foreach ($teams as $teamData) {
            $nation = Nation::where('code', $teamData['nation_code'])->firstOrFail();

            Team::updateOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'nation_id' => $nation->id,
                ],
                [
                    'group_name' => $teamData['group'],
                    'points' => 0,
                    'goals_for' => 0,
                    'goals_against' => 0,
                    'wins' => 0,
                    'draws' => 0,
                    'losses' => 0,
                ]
            );
        }

        $this->command->info('Teams seeded: ' . count($teams));
    }

    private function seedGames(Tournament $tournament): void
    {
        $games = require __DIR__ . '/data/games.php';
        $count = 0;

        foreach ($games as $gameData) {
            $location = Location::where('slug', $gameData['location'])->firstOrFail();

            $game = [
                'tournament_id' => $tournament->id,
                'game_type' => $gameData['type'],
                'kickoff_at' => Carbon::parse($gameData['kickoff_at']),
                'location_id' => $location->id,
                'is_finished' => false,
            ];

            if ($gameData['type'] === 'GROUP') {
                // Group game - resolve team references
                $game['group_name'] = $gameData['group'];
                $game['home_team_id'] = $this->resolveTeamId($tournament, $gameData['home']);
                $game['visitor_team_id'] = $this->resolveTeamId($tournament, $gameData['visitor']);
            } else {
                // Knockout game - use placeholders
                $game['name'] = $gameData['name'];
                $game['home_team_placeholder'] = $gameData['home_placeholder'];
                $game['visitor_team_placeholder'] = $gameData['visitor_placeholder'];
            }

            Game::updateOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'kickoff_at' => $game['kickoff_at'],
                    'game_type' => $game['game_type'],
                ],
                $game
            );

            $count++;
        }

        $this->command->info('Games seeded: ' . $count);
    }

    /**
     * Resolve team reference like 'A1' to actual team ID
     */
    private function resolveTeamId(Tournament $tournament, string $reference): int
    {
        // Reference format: 'A1', 'B2', etc.
        // A = group, 1 = position in group (1-4)
        $group = $reference[0];
        $position = (int) $reference[1];

        $team = Team::where('tournament_id', $tournament->id)
            ->where('group_name', $group)
            ->whereHas('nation', function ($query) use ($group, $position) {
                // This assumes teams are ordered by position in the data file
                // Alternatively, you could add a 'draw_position' column
            })
            ->get()
            ->values()
            ->get($position - 1);

        if (!$team) {
            // Fallback: get team by group and position order
            $teams = Team::where('tournament_id', $tournament->id)
                ->where('group_name', $group)
                ->with('nation')
                ->get();

            // Map positions based on original draw
            $teamsData = require __DIR__ . '/data/teams.php';
            $groupTeams = collect($teamsData)->where('group', $group)->values();

            $nationCode = $groupTeams->get($position - 1)['nation_code'];
            $nation = Nation::where('code', $nationCode)->first();

            $team = $teams->firstWhere('nation_id', $nation->id);
        }

        return $team->id;
    }

    private function seedSpecialTippSpecs(Tournament $tournament): void
    {
        $specs = require __DIR__ . '/data/special_tipp_specs.php';

        foreach ($specs as $spec) {
            $data = [
                'tournament_id' => $tournament->id,
                'name' => $spec['name'],
                'label' => $spec['label'],
                'type' => $spec['type'],
                'value' => $spec['value'],
            ];

            // Link to specific team if defined
            if (!empty($spec['team_code'])) {
                $nation = Nation::where('code', $spec['team_code'])->first();
                $team = Team::where('tournament_id', $tournament->id)
                    ->where('nation_id', $nation->id)
                    ->first();
                $data['team_id'] = $team?->id;
            }

            // Link to group if defined
            if (!empty($spec['group'])) {
                $data['group_name'] = $spec['group'];
            }

            SpecialTippSpec::updateOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'name' => $spec['name'],
                ],
                $data
            );
        }

        $this->command->info('Special Tipp Specs seeded: ' . count($specs));
    }
}
