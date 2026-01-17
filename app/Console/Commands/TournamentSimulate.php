<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameTipp;
use App\Models\SpecialTipp;
use App\Models\SpecialTippSpec;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Models\UserScore;
use App\Models\UserScoreHistory;
use App\Services\ScoreCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class TournamentSimulate extends Command
{
    protected $signature = 'tournament:simulate
                            {--users=100 : Number of users to create}
                            {--finish-all : Finish all games at once}
                            {--finish-next : Finish the next unfinished game}
                            {--finish-count=1 : Number of games to finish}
                            {--reset : Reset all simulated data}
                            {--create-users : Only create users with tipps}';

    protected $description = 'Simulate tournament with fake users and results';

    private array $maleNames = [
        'Alexander', 'Andreas', 'Benjamin', 'Christian', 'Daniel', 'David', 'Dennis', 'Dominik',
        'Eric', 'Fabian', 'Felix', 'Florian', 'Frank', 'Hans', 'Jan', 'Jonas', 'Julian', 'Kevin',
        'Lars', 'Leon', 'Lukas', 'Marcel', 'Marco', 'Mario', 'Markus', 'Martin', 'Mathias', 'Max',
        'Michael', 'Moritz', 'Nico', 'Niklas', 'Oliver', 'Patrick', 'Paul', 'Peter', 'Philipp',
        'Rafael', 'Ralf', 'Robert', 'Robin', 'Sebastian', 'Simon', 'Stefan', 'Steffen', 'Thomas',
        'Tim', 'Tobias', 'Wolfgang', 'Uwe', 'Klaus', 'Jürgen', 'Werner', 'Helmut', 'Dieter',
        'Rainer', 'Bernd', 'Holger', 'Torsten', 'Sven', 'Kai', 'Dirk', 'Jens', 'Carsten', 'Björn',
        'Henrik', 'Bastian', 'Christoph', 'Sascha', 'René', 'Maximilian', 'Friedrich', 'Heinrich',
    ];

    private array $femaleNames = [
        'Alexandra', 'Andrea', 'Angela', 'Angelika', 'Anna', 'Annette', 'Barbara', 'Birgit',
        'Brigitte', 'Carina', 'Carmen', 'Caroline', 'Charlotte', 'Christina', 'Claudia', 'Daniela',
        'Diana', 'Elena', 'Elisabeth', 'Emma', 'Eva', 'Franziska', 'Gabriele', 'Hannah', 'Heike',
        'Helena', 'Ingrid', 'Jana', 'Jennifer', 'Jessica', 'Julia', 'Juliane', 'Karin', 'Katharina',
        'Katja', 'Kerstin', 'Klara', 'Lara', 'Laura', 'Lea', 'Lena', 'Leonie', 'Lisa', 'Luisa',
        'Manuela', 'Maria', 'Marie', 'Marina', 'Martina', 'Melanie', 'Michaela', 'Monika', 'Nadine',
        'Nicole', 'Nina', 'Petra', 'Regina', 'Renate', 'Sabine', 'Sandra', 'Sara', 'Sarah', 'Silke',
        'Simone', 'Sonja', 'Sophie', 'Stefanie', 'Susanne', 'Tanja', 'Teresa', 'Ursula', 'Vanessa',
    ];

    private array $lastNames = [
        'Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker',
        'Schulz', 'Hoffmann', 'Schäfer', 'Koch', 'Bauer', 'Richter', 'Klein', 'Wolf', 'Schröder',
        'Neumann', 'Schwarz', 'Zimmermann', 'Braun', 'Krüger', 'Hofmann', 'Hartmann', 'Lange',
        'Schmitt', 'Werner', 'Schmitz', 'Krause', 'Meier', 'Lehmann', 'Schmid', 'Schulze',
        'Maier', 'Köhler', 'Herrmann', 'König', 'Walter', 'Mayer', 'Huber', 'Kaiser', 'Fuchs',
        'Peters', 'Lang', 'Scholz', 'Möller', 'Weiß', 'Jung', 'Hahn', 'Schubert', 'Vogel',
        'Friedrich', 'Keller', 'Günther', 'Frank', 'Berger', 'Winkler', 'Roth', 'Beck', 'Lorenz',
        'Baumann', 'Franke', 'Albrecht', 'Schuster', 'Simon', 'Ludwig', 'Böhm', 'Winter',
        'Kraus', 'Martin', 'Schumacher', 'Krämer', 'Vogt', 'Stein', 'Jäger', 'Otto', 'Sommer',
        'Groß', 'Seidel', 'Heinrich', 'Brandt', 'Haas', 'Schreiber', 'Graf', 'Schulte', 'Dietrich',
        'Ziegler', 'Kuhn', 'Kühn', 'Pohl', 'Engel', 'Horn', 'Busch', 'Bergmann', 'Thomas',
        'Voigt', 'Sauer', 'Arnold', 'Wolff', 'Pfeiffer',
    ];

    // Real World Cup 2022 results
    private array $realResults = [
        // Group Stage - Day 1-3
        ['QAT', 'ECU', 0, 2], ['ENG', 'IRN', 6, 2], ['SEN', 'NED', 0, 2], ['USA', 'WAL', 1, 1],
        ['ARG', 'KSA', 1, 2], ['DEN', 'TUN', 0, 0], ['MEX', 'POL', 0, 0], ['FRA', 'AUS', 4, 1],
        ['MAR', 'CRO', 0, 0], ['GER', 'JPN', 1, 2], ['ESP', 'CRC', 7, 0], ['BEL', 'CAN', 1, 0],
        ['SUI', 'CMR', 1, 0], ['URU', 'KOR', 0, 0], ['POR', 'GHA', 3, 2], ['BRA', 'SRB', 2, 0],
        // Group Stage - Day 4-6
        ['WAL', 'IRN', 0, 2], ['QAT', 'SEN', 1, 3], ['NED', 'ECU', 1, 1], ['ENG', 'USA', 0, 0],
        ['TUN', 'AUS', 0, 1], ['POL', 'KSA', 2, 0], ['FRA', 'DEN', 2, 1], ['ARG', 'MEX', 2, 0],
        ['JPN', 'CRC', 0, 1], ['BEL', 'MAR', 0, 2], ['CRO', 'CAN', 4, 1], ['ESP', 'GER', 1, 1],
        ['CMR', 'SRB', 3, 3], ['KOR', 'GHA', 2, 3], ['BRA', 'SUI', 1, 0], ['POR', 'URU', 2, 0],
        // Group Stage - Day 7-8
        ['NED', 'QAT', 2, 0], ['ECU', 'SEN', 1, 2], ['WAL', 'ENG', 0, 3], ['IRN', 'USA', 0, 1],
        ['AUS', 'DEN', 1, 0], ['TUN', 'FRA', 1, 0], ['POL', 'ARG', 0, 2], ['KSA', 'MEX', 1, 2],
        ['CRO', 'BEL', 0, 0], ['CAN', 'MAR', 1, 2], ['JPN', 'ESP', 2, 1], ['CRC', 'GER', 2, 4],
        ['GHA', 'URU', 0, 2], ['KOR', 'POR', 2, 1], ['SRB', 'SUI', 2, 3], ['CMR', 'BRA', 1, 0],
        // Round of 16
        ['NED', 'USA', 3, 1], ['ARG', 'AUS', 2, 1], ['FRA', 'POL', 3, 1], ['ENG', 'SEN', 3, 0],
        ['JPN', 'CRO', 1, 1, 'CRO'], ['BRA', 'KOR', 4, 1], ['MAR', 'ESP', 0, 0, 'MAR'], ['POR', 'SUI', 6, 1],
        // Quarter Finals
        ['CRO', 'BRA', 1, 1, 'CRO'], ['NED', 'ARG', 2, 2, 'ARG'], ['MAR', 'POR', 1, 0], ['ENG', 'FRA', 1, 2],
        // Semi Finals
        ['ARG', 'CRO', 3, 0], ['FRA', 'MAR', 2, 0],
        // Third Place
        ['CRO', 'MAR', 2, 1],
        // Final
        ['ARG', 'FRA', 3, 3, 'ARG'],
    ];

    public function handle(): int
    {
        $tournament = Tournament::where('is_active', true)->first();

        if (!$tournament) {
            $this->error('No active tournament found.');
            return 1;
        }

        if ($this->option('reset')) {
            return $this->resetSimulation($tournament);
        }

        if ($this->option('create-users')) {
            return $this->createUsersWithTipps($tournament, (int) $this->option('users'));
        }

        if ($this->option('finish-all')) {
            return $this->finishAllGames($tournament);
        }

        if ($this->option('finish-next')) {
            return $this->finishNextGames($tournament, (int) $this->option('finish-count'));
        }

        // Default: create users and finish all
        $this->createUsersWithTipps($tournament, (int) $this->option('users'));
        $this->finishAllGames($tournament);

        return 0;
    }

    private function createUsersWithTipps(Tournament $tournament, int $count): int
    {
        $this->info("Creating {$count} users with German names...");

        // Load games with teams and nations for FIFA rankings
        $games = Game::where('tournament_id', $tournament->id)
            ->whereNotNull('home_team_id')
            ->whereNotNull('visitor_team_id')
            ->with(['homeTeam.nation', 'visitorTeam.nation'])
            ->get();

        $teams = Team::where('tournament_id', $tournament->id)
            ->with('nation')
            ->get();

        $specialSpecs = SpecialTippSpec::where('tournament_id', $tournament->id)->get();

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $usedNames = [];

        for ($i = 0; $i < $count; $i++) {
            // Generate unique name
            do {
                $isMale = rand(0, 1) === 1;
                $firstName = $isMale
                    ? $this->maleNames[array_rand($this->maleNames)]
                    : $this->femaleNames[array_rand($this->femaleNames)];
                $lastName = $this->lastNames[array_rand($this->lastNames)];
                $fullName = "{$firstName} {$lastName}";
            } while (in_array($fullName, $usedNames));

            $usedNames[] = $fullName;

            // Create user
            $user = User::create([
                'name' => $fullName,
                'email' => strtolower(str_replace(' ', '.', $this->removeUmlauts($fullName))) . '_' . ($i + 1) . '@example.com',
                'password' => Hash::make('password'),
                'is_simulated' => true,
            ]);

            // Create tipps for all games (FIFA ranking aware)
            foreach ($games as $game) {
                $this->createGameTippForUser($user->id, $game);
            }

            // Create special tipps (FIFA ranking aware)
            foreach ($specialSpecs as $spec) {
                $this->createSpecialTippForUser($user->id, $spec, $teams);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Created {$count} users with tipps for all games.");

        // Also create tipps for user ID 1 (admin) if they don't have any
        $this->createTippsForUser(1, $games, $teams, $specialSpecs);

        return 0;
    }

    private function createGameTippForUser(int $userId, Game $game): void
    {
        $homeRanking = $game->homeTeam?->nation?->fifa_ranking ?? 50;
        $visitorRanking = $game->visitorTeam?->nation?->fifa_ranking ?? 50;

        // Generate score based on FIFA rankings
        [$goalsHome, $goalsVisitor] = $this->generateRankingAwareScore($homeRanking, $visitorRanking);

        $penaltyWinner = null;
        if ($game->isKnockoutGame() && $goalsHome === $goalsVisitor && $game->home_team_id && $game->visitor_team_id) {
            // Higher ranked team more likely to win penalties
            $penaltyWinner = $this->pickWinnerByRanking(
                $game->home_team_id,
                $homeRanking,
                $game->visitor_team_id,
                $visitorRanking
            );
        }

        GameTipp::create([
            'user_id' => $userId,
            'game_id' => $game->id,
            'goals_home' => $goalsHome,
            'goals_visitor' => $goalsVisitor,
            'penalty_winner_team_id' => $penaltyWinner,
        ]);
    }

    private function createSpecialTippForUser(int $userId, SpecialTippSpec $spec, $teams): void
    {
        if ($spec->type === 'TEAM') {
            $eligibleTeams = $this->getEligibleTeamsForSpec($spec, $teams);
            if ($eligibleTeams->isNotEmpty()) {
                // Pick team weighted by FIFA ranking (lower ranking = better = more likely)
                $selectedTeam = $this->pickTeamByRanking($eligibleTeams);
                SpecialTipp::create([
                    'user_id' => $userId,
                    'special_tipp_spec_id' => $spec->id,
                    'predicted_team_id' => $selectedTeam->id,
                ]);
            }
        } elseif ($spec->type === 'TOTAL_GOALS') {
            SpecialTipp::create([
                'user_id' => $userId,
                'special_tipp_spec_id' => $spec->id,
                'predicted_value' => rand(2, 12),
            ]);
        }
    }

    private function createTippsForUser(int $userId, $games, $teams, $specialSpecs): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $existingTipps = GameTipp::where('user_id', $userId)->count();
        if ($existingTipps > 0) {
            $this->info("User {$user->name} already has tipps, skipping.");
            return;
        }

        $this->info("Creating tipps for {$user->name}...");

        foreach ($games as $game) {
            $this->createGameTippForUser($userId, $game);
        }

        foreach ($specialSpecs as $spec) {
            $existingSpecialTipp = SpecialTipp::where('user_id', $userId)
                ->where('special_tipp_spec_id', $spec->id)
                ->exists();

            if (!$existingSpecialTipp) {
                $this->createSpecialTippForUser($userId, $spec, $teams);
            }
        }

        $this->info("Created tipps for {$user->name}.");
    }

    /**
     * Generate a score that respects FIFA rankings.
     * Lower FIFA ranking = better team = more likely to score more / concede less.
     */
    private function generateRankingAwareScore(int $homeRanking, int $visitorRanking): array
    {
        // Calculate ranking difference (positive = home team is better)
        $rankingDiff = $visitorRanking - $homeRanking;

        // Base probabilities for outcomes
        // rankingDiff > 0 means home is better ranked
        $homeWinChance = 40 + ($rankingDiff * 0.5); // Base 40%, adjusted by ranking diff
        $drawChance = 25;
        $visitorWinChance = 100 - $homeWinChance - $drawChance;

        // Clamp values
        $homeWinChance = max(15, min(70, $homeWinChance));
        $visitorWinChance = max(15, min(70, $visitorWinChance));

        $rand = rand(1, 100);

        if ($rand <= $homeWinChance) {
            // Home win
            $goalsHome = $this->generateGoalsForWinner();
            $goalsVisitor = $this->generateGoalsForLoser($goalsHome);
        } elseif ($rand <= $homeWinChance + $drawChance) {
            // Draw
            $goalsHome = $this->generateRealisticGoals();
            $goalsVisitor = $goalsHome;
        } else {
            // Visitor win
            $goalsVisitor = $this->generateGoalsForWinner();
            $goalsHome = $this->generateGoalsForLoser($goalsVisitor);
        }

        return [$goalsHome, $goalsVisitor];
    }

    /**
     * Pick a winner based on FIFA rankings (lower = better = more likely to win).
     */
    private function pickWinnerByRanking(int $teamAId, int $rankingA, int $teamBId, int $rankingB): int
    {
        // Convert rankings to weights (lower ranking = higher weight)
        $maxRanking = max($rankingA, $rankingB) + 10;
        $weightA = $maxRanking - $rankingA;
        $weightB = $maxRanking - $rankingB;

        $total = $weightA + $weightB;
        $rand = rand(1, $total);

        return $rand <= $weightA ? $teamAId : $teamBId;
    }

    /**
     * Pick a team weighted by FIFA ranking.
     * Teams with lower FIFA ranking (= better) are more likely to be picked.
     */
    private function pickTeamByRanking($teams)
    {
        if ($teams->isEmpty()) {
            return null;
        }

        // Calculate weights based on FIFA ranking
        // Lower ranking = better = higher weight
        $maxRanking = $teams->max(fn($t) => $t->nation?->fifa_ranking ?? 100) + 10;

        $weights = [];
        foreach ($teams as $team) {
            $ranking = $team->nation?->fifa_ranking ?? 50;
            // Also factor in champion_count for world cup winner prediction
            $championBonus = ($team->nation?->champion_count ?? 0) * 5;
            $weights[$team->id] = ($maxRanking - $ranking) + $championBonus;
        }

        $totalWeight = array_sum($weights);
        $rand = rand(1, $totalWeight);

        $cumulative = 0;
        foreach ($weights as $teamId => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $teams->firstWhere('id', $teamId);
            }
        }

        return $teams->first();
    }

    private function generateGoalsForWinner(): int
    {
        $weights = [
            1 => 35,
            2 => 35,
            3 => 18,
            4 => 8,
            5 => 3,
            6 => 1,
        ];

        return $this->weightedRandom($weights);
    }

    private function generateGoalsForLoser(int $winnerGoals): int
    {
        // Loser must have fewer goals than winner
        if ($winnerGoals <= 1) {
            return 0;
        }

        $maxGoals = $winnerGoals - 1;
        $weights = [];
        for ($i = 0; $i <= $maxGoals; $i++) {
            $weights[$i] = match ($i) {
                0 => 40,
                1 => 35,
                2 => 18,
                3 => 5,
                default => 2,
            };
        }

        return $this->weightedRandom($weights);
    }

    private function finishAllGames(Tournament $tournament): int
    {
        $games = Game::where('tournament_id', $tournament->id)
            ->where('is_finished', false)
            ->with(['homeTeam.nation', 'visitorTeam.nation'])
            ->orderBy('kickoff_at')
            ->get();

        if ($games->isEmpty()) {
            $this->info('All games are already finished.');
            return 0;
        }

        $this->info("Finishing {$games->count()} games...");

        $scoreService = app(ScoreCalculationService::class);
        $bar = $this->output->createProgressBar($games->count());
        $bar->start();

        foreach ($games as $game) {
            $this->finishGame($game, $scoreService);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Calculate special tipp scores
        $scoreService->calculateSpecialTippScores($tournament->id);

        $this->info('All games finished and scores calculated.');

        return 0;
    }

    private function finishNextGames(Tournament $tournament, int $count): int
    {
        $games = Game::where('tournament_id', $tournament->id)
            ->where('is_finished', false)
            ->with(['homeTeam.nation', 'visitorTeam.nation'])
            ->orderBy('kickoff_at')
            ->limit($count)
            ->get();

        if ($games->isEmpty()) {
            $this->info('All games are already finished.');
            return 0;
        }

        $scoreService = app(ScoreCalculationService::class);

        foreach ($games as $game) {
            $this->finishGame($game, $scoreService);

            $homeTeam = $game->homeTeam?->nation->code ?? '???';
            $visitorTeam = $game->visitorTeam?->nation->code ?? '???';
            $this->info("Finished: {$homeTeam} {$game->goals_home} - {$game->goals_visitor} {$visitorTeam}");
        }

        $this->info("Finished {$games->count()} game(s). Rankings updated.");

        return 0;
    }

    private function finishGame(Game $game, ScoreCalculationService $scoreService): void
    {
        // Try to find real result first
        $result = $this->findRealResult($game);

        if ($result) {
            $game->goals_home = $result['goals_home'];
            $game->goals_visitor = $result['goals_visitor'];
            $game->has_penalty_shootout = isset($result['penalty_winner']);
            if (isset($result['penalty_winner'])) {
                $winnerTeam = Team::whereHas('nation', fn($q) => $q->where('code', $result['penalty_winner']))
                    ->where('tournament_id', $game->tournament_id)
                    ->first();
                $game->penalty_winner_team_id = $winnerTeam?->id;
            }
        } else {
            // Generate ranking-aware result
            $homeRanking = $game->homeTeam?->nation?->fifa_ranking ?? 50;
            $visitorRanking = $game->visitorTeam?->nation?->fifa_ranking ?? 50;

            [$goalsHome, $goalsVisitor] = $this->generateRankingAwareScore($homeRanking, $visitorRanking);

            $game->goals_home = $goalsHome;
            $game->goals_visitor = $goalsVisitor;

            if ($game->isKnockoutGame() && $goalsHome === $goalsVisitor && $game->home_team_id && $game->visitor_team_id) {
                $game->has_penalty_shootout = true;
                $game->penalty_winner_team_id = $this->pickWinnerByRanking(
                    $game->home_team_id,
                    $homeRanking,
                    $game->visitor_team_id,
                    $visitorRanking
                );
            }
        }

        $game->is_finished = true;
        $game->save();

        $scoreService->calculateGameScores($game);
    }

    private function findRealResult(Game $game): ?array
    {
        $homeCode = $game->homeTeam?->nation->code;
        $visitorCode = $game->visitorTeam?->nation->code;

        if (!$homeCode || !$visitorCode) {
            return null;
        }

        // Map some codes
        $codeMap = [
            'GB-ENG' => 'ENG',
            'GB-WLS' => 'WAL',
            'SA' => 'KSA',
            'IR' => 'IRN',
            'KR' => 'KOR',
            'CR' => 'CRC',
            'RS' => 'SRB',
            'CH' => 'SUI',
        ];

        $homeCode = strtoupper($codeMap[$homeCode] ?? $homeCode);
        $visitorCode = strtoupper($codeMap[$visitorCode] ?? $visitorCode);

        foreach ($this->realResults as $result) {
            if ($result[0] === $homeCode && $result[1] === $visitorCode) {
                return [
                    'goals_home' => $result[2],
                    'goals_visitor' => $result[3],
                    'penalty_winner' => $result[4] ?? null,
                ];
            }
        }

        return null;
    }

    private function resetSimulation(Tournament $tournament): int
    {
        $this->warn('This will delete all simulated users and their data.');

        if (!$this->confirm('Are you sure you want to reset?')) {
            return 0;
        }

        $this->info('Resetting simulation...');

        // Delete simulated users (cascades to tipps)
        $deletedUsers = User::where('is_simulated', true)->delete();

        // Reset games
        Game::where('tournament_id', $tournament->id)->update([
            'is_finished' => false,
            'goals_home' => null,
            'goals_visitor' => null,
            'goals_home_halftime' => null,
            'goals_visitor_halftime' => null,
            'has_penalty_shootout' => false,
            'penalty_winner_team_id' => null,
        ]);

        // Reset team stats
        Team::where('tournament_id', $tournament->id)->update([
            'points' => 0,
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
        ]);

        // Clear user scores and history
        UserScore::where('tournament_id', $tournament->id)->delete();
        UserScoreHistory::where('tournament_id', $tournament->id)->delete();

        // Reset special tipps scores
        SpecialTipp::whereHas('specialTippSpec', fn($q) => $q->where('tournament_id', $tournament->id))
            ->update(['score' => 0]);

        $this->info("Reset complete. Deleted {$deletedUsers} simulated users.");

        return 0;
    }

    private function generateRealisticGoals(): int
    {
        $weights = [
            0 => 25,
            1 => 30,
            2 => 25,
            3 => 12,
            4 => 5,
            5 => 2,
            6 => 1,
        ];

        return $this->weightedRandom($weights);
    }

    private function weightedRandom(array $weights): int
    {
        $total = array_sum($weights);
        $rand = rand(1, $total);

        $cumulative = 0;
        foreach ($weights as $value => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $value;
            }
        }

        return array_key_first($weights);
    }

    private function getEligibleTeamsForSpec(SpecialTippSpec $spec, $teams)
    {
        if (str_starts_with($spec->name, 'WINNER_GROUP_')) {
            $group = substr($spec->name, -1);
            return $teams->where('group_name', $group);
        }

        return $teams;
    }

    private function removeUmlauts(string $str): string
    {
        return str_replace(
            ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
            ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'],
            $str
        );
    }
}
