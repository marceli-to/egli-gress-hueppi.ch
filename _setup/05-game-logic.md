# Game Logic & Scoring System

## Scoring Overview

The prediction game awards points based on how accurate predictions are:

| Accuracy Level | Points | Description |
|----------------|--------|-------------|
| Exact Score | 4 | Both home and visitor goals correct |
| Goal Difference | 3 | Correct difference (e.g., 2-1 predicted, 3-2 actual) |
| Tendency | 1 | Correct outcome (home win/draw/away win) |
| Wrong | 0 | Incorrect outcome prediction |

## Original Java Implementation

From `tippspiel/src/main/java/ch/erzberg/tippspiel/tipp/entity/Tipp.java`:

```java
public void calculateScore() {
    this.score = 0;

    if (goalsHomeCorrect && goalsVisitorCorrect) {
        this.score = 4;  // Exact score
    } else if (goalsDifferenceCorrect) {
        this.score = 3;  // Goal difference
    } else if (winnerCorrect) {
        this.score = 1;  // Tendency only
    }
}
```

## Laravel Implementation

### ScoringService.php

```php
<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameTipp;
use App\Models\Tournament;
use App\Models\UserScore;

class ScoringService
{
    /**
     * Calculate score for a single tipp
     */
    public function calculateTippScore(GameTipp $tipp, Game $game): int
    {
        if (!$game->is_finished) {
            return 0;
        }

        // Reset flags
        $tipp->is_tendency_correct = false;
        $tipp->is_difference_correct = false;
        $tipp->is_goals_home_correct = false;
        $tipp->is_goals_visitor_correct = false;

        $actualHome = $game->goals_home;
        $actualVisitor = $game->goals_visitor;
        $predictedHome = $tipp->goals_home;
        $predictedVisitor = $tipp->goals_visitor;

        // Check exact goals
        $homeCorrect = $actualHome === $predictedHome;
        $visitorCorrect = $actualVisitor === $predictedVisitor;

        $tipp->is_goals_home_correct = $homeCorrect;
        $tipp->is_goals_visitor_correct = $visitorCorrect;

        // Exact score = 4 points
        if ($homeCorrect && $visitorCorrect) {
            $tipp->is_tendency_correct = true;
            $tipp->is_difference_correct = true;
            return 4;
        }

        // Check goal difference
        $actualDiff = $actualHome - $actualVisitor;
        $predictedDiff = $predictedHome - $predictedVisitor;

        if ($actualDiff === $predictedDiff) {
            $tipp->is_tendency_correct = true;
            $tipp->is_difference_correct = true;
            return 3;
        }

        // Check tendency (winner/draw)
        $actualTendency = $this->getTendency($actualHome, $actualVisitor);
        $predictedTendency = $this->getTendency($predictedHome, $predictedVisitor);

        if ($actualTendency === $predictedTendency) {
            $tipp->is_tendency_correct = true;
            return 1;
        }

        return 0;
    }

    /**
     * Get match tendency: 1 = home win, 0 = draw, -1 = away win
     */
    private function getTendency(int $home, int $visitor): int
    {
        if ($home > $visitor) return 1;
        if ($home < $visitor) return -1;
        return 0;
    }

    /**
     * Recalculate all tipps for a game after result is entered
     */
    public function processGameResult(Game $game): void
    {
        $tipps = $game->tipps()->get();

        foreach ($tipps as $tipp) {
            $tipp->score = $this->calculateTippScore($tipp, $game);
            $tipp->save();
        }

        // Update all user scores
        $this->updateAllUserScores($game->tournament);
    }

    /**
     * Update user scores for tournament
     */
    public function updateAllUserScores(Tournament $tournament): void
    {
        $users = UserScore::where('tournament_id', $tournament->id)
            ->pluck('user_id');

        foreach ($users as $userId) {
            $this->updateUserScore($userId, $tournament);
        }

        // Recalculate ranks
        $this->updateRanks($tournament);
    }

    /**
     * Update single user's score
     */
    public function updateUserScore(int $userId, Tournament $tournament): void
    {
        // Calculate game points
        $gamePoints = GameTipp::where('user_id', $userId)
            ->whereHas('game', fn($q) => $q
                ->where('tournament_id', $tournament->id)
                ->where('is_finished', true)
            )
            ->sum('score');

        // Calculate special points
        $specialPoints = SpecialTipp::where('user_id', $userId)
            ->whereHas('spec', fn($q) => $q->where('tournament_id', $tournament->id))
            ->sum('score');

        // Count tipps
        $tippCount = GameTipp::where('user_id', $userId)
            ->whereHas('game', fn($q) => $q
                ->where('tournament_id', $tournament->id)
                ->where('is_finished', true)
            )
            ->count();

        $totalPoints = $gamePoints + $specialPoints;
        $average = $tippCount > 0 ? round($totalPoints / $tippCount, 2) : 0;

        UserScore::updateOrCreate(
            ['user_id' => $userId, 'tournament_id' => $tournament->id],
            [
                'total_points' => $totalPoints,
                'game_points' => $gamePoints,
                'special_points' => $specialPoints,
                'tipp_count' => $tippCount,
                'average_score' => $average,
            ]
        );
    }

    /**
     * Update ranking positions
     */
    public function updateRanks(Tournament $tournament): void
    {
        $scores = UserScore::where('tournament_id', $tournament->id)
            ->orderByDesc('total_points')
            ->orderByDesc('average_score')
            ->get();

        $rank = 0;
        $previousPoints = null;
        $previousAvg = null;

        foreach ($scores as $score) {
            // Same rank for tied scores
            if ($score->total_points !== $previousPoints ||
                $score->average_score !== $previousAvg) {
                $rank++;
            }

            $oldRank = $score->rank;
            $score->rank = $rank;
            $score->rank_delta = $oldRank ? ($oldRank - $rank) : 0;
            $score->save();

            $previousPoints = $score->total_points;
            $previousAvg = $score->average_score;
        }

        // Record history
        $this->recordScoreHistory($tournament);
    }

    /**
     * Record score history for charts
     */
    public function recordScoreHistory(Tournament $tournament): void
    {
        $gameDay = Game::where('tournament_id', $tournament->id)
            ->where('is_finished', true)
            ->count();

        $scores = UserScore::where('tournament_id', $tournament->id)->get();

        foreach ($scores as $score) {
            UserScoreHistory::updateOrCreate(
                [
                    'user_id' => $score->user_id,
                    'tournament_id' => $tournament->id,
                    'game_day' => $gameDay,
                ],
                [
                    'points' => $score->total_points,
                    'rank' => $score->rank,
                    'rank_delta' => $score->rank_delta,
                ]
            );
        }
    }
}
```

## Special Tipps Logic

### Types of Special Tipps

1. **Winner Tipp** - Predict tournament champion
2. **Final Ranking Tipp** - Predict top 4 teams
3. **Total Goals Tipp** - Predict total goals in tournament

### SpecialTippService.php

```php
<?php

namespace App\Services;

use App\Models\SpecialTipp;
use App\Models\SpecialTippSpec;
use App\Models\Tournament;

class SpecialTippService
{
    /**
     * Evaluate winner tipp (after final is played)
     */
    public function evaluateWinnerTipps(Tournament $tournament): void
    {
        $winnerSpec = SpecialTippSpec::where('tournament_id', $tournament->id)
            ->where('type', 'WINNER')
            ->first();

        if (!$winnerSpec || !$winnerSpec->team_id) {
            return; // Winner not yet determined
        }

        $tipps = SpecialTipp::where('special_tipp_spec_id', $winnerSpec->id)->get();

        foreach ($tipps as $tipp) {
            $tipp->score = $tipp->predicted_team_id === $winnerSpec->team_id
                ? $winnerSpec->value
                : 0;
            $tipp->save();
        }
    }

    /**
     * Evaluate total goals tipp (after all games played)
     */
    public function evaluateTotalGoalsTipps(Tournament $tournament): void
    {
        $spec = SpecialTippSpec::where('tournament_id', $tournament->id)
            ->where('type', 'TOTAL_GOALS')
            ->first();

        if (!$spec) return;

        // Calculate actual total goals
        $totalGoals = Game::where('tournament_id', $tournament->id)
            ->where('is_finished', true)
            ->sum(\DB::raw('goals_home + goals_visitor'));

        $tipps = SpecialTipp::where('special_tipp_spec_id', $spec->id)->get();

        foreach ($tipps as $tipp) {
            // Exact match = full points, close = partial
            $diff = abs($tipp->predicted_value - $totalGoals);

            if ($diff === 0) {
                $tipp->score = $spec->value;
            } elseif ($diff <= 5) {
                $tipp->score = (int) ($spec->value * 0.5);
            } elseif ($diff <= 10) {
                $tipp->score = (int) ($spec->value * 0.25);
            } else {
                $tipp->score = 0;
            }

            $tipp->save();
        }
    }
}
```

## Group Stage Team Ranking

FIFA World Cup ranking rules implementation:

### GroupRankingService.php

```php
<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Collection;

class GroupRankingService
{
    /**
     * Calculate group standings
     */
    public function calculateGroupStandings(string $groupName, int $tournamentId): Collection
    {
        $teams = Team::where('tournament_id', $tournamentId)
            ->where('group_name', $groupName)
            ->with(['homeGames' => fn($q) => $q->where('is_finished', true),
                    'visitorGames' => fn($q) => $q->where('is_finished', true)])
            ->get();

        // Calculate stats for each team
        foreach ($teams as $team) {
            $this->calculateTeamStats($team);
        }

        // Sort by FIFA rules
        return $this->sortByFifaRules($teams);
    }

    /**
     * Calculate team statistics from games
     */
    private function calculateTeamStats(Team $team): void
    {
        $wins = 0;
        $draws = 0;
        $losses = 0;
        $goalsFor = 0;
        $goalsAgainst = 0;

        // Home games
        foreach ($team->homeGames as $game) {
            $goalsFor += $game->goals_home;
            $goalsAgainst += $game->goals_visitor;

            if ($game->goals_home > $game->goals_visitor) $wins++;
            elseif ($game->goals_home === $game->goals_visitor) $draws++;
            else $losses++;
        }

        // Visitor games
        foreach ($team->visitorGames as $game) {
            $goalsFor += $game->goals_visitor;
            $goalsAgainst += $game->goals_home;

            if ($game->goals_visitor > $game->goals_home) $wins++;
            elseif ($game->goals_visitor === $game->goals_home) $draws++;
            else $losses++;
        }

        $team->wins = $wins;
        $team->draws = $draws;
        $team->losses = $losses;
        $team->goals_for = $goalsFor;
        $team->goals_against = $goalsAgainst;
        $team->points = ($wins * 3) + $draws;
        $team->save();
    }

    /**
     * Sort teams by FIFA World Cup rules
     */
    private function sortByFifaRules(Collection $teams): Collection
    {
        return $teams->sort(function ($a, $b) {
            // 1. Points
            if ($a->points !== $b->points) {
                return $b->points - $a->points;
            }

            // 2. Goal difference
            $diffA = $a->goals_for - $a->goals_against;
            $diffB = $b->goals_for - $b->goals_against;
            if ($diffA !== $diffB) {
                return $diffB - $diffA;
            }

            // 3. Goals scored
            if ($a->goals_for !== $b->goals_for) {
                return $b->goals_for - $a->goals_for;
            }

            // 4. Head-to-head (if only 2 teams tied)
            $h2h = $this->getHeadToHead($a, $b);
            if ($h2h !== 0) {
                return $h2h;
            }

            // 5. Fair play points (lower is better)
            if ($a->fair_play_points !== $b->fair_play_points) {
                return $a->fair_play_points - $b->fair_play_points;
            }

            // 6. FIFA ranking
            return ($a->nation->fifa_ranking ?? 999) - ($b->nation->fifa_ranking ?? 999);
        })->values();
    }

    /**
     * Get head-to-head result between two teams
     * Returns: positive if $a wins, negative if $b wins, 0 if tied
     */
    private function getHeadToHead(Team $a, Team $b): int
    {
        $game = Game::where(function ($q) use ($a, $b) {
                $q->where('home_team_id', $a->id)->where('visitor_team_id', $b->id);
            })
            ->orWhere(function ($q) use ($a, $b) {
                $q->where('home_team_id', $b->id)->where('visitor_team_id', $a->id);
            })
            ->where('is_finished', true)
            ->first();

        if (!$game) return 0;

        $aGoals = $game->home_team_id === $a->id ? $game->goals_home : $game->goals_visitor;
        $bGoals = $game->home_team_id === $b->id ? $game->goals_home : $game->goals_visitor;

        return $bGoals - $aGoals; // Negative means A wins
    }
}
```

## Tipp Validation Rules

### TippValidationService.php

```php
<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameTipp;
use Illuminate\Validation\ValidationException;

class TippValidationService
{
    /**
     * Validate a tipp can be submitted
     */
    public function validateTipp(Game $game, array $data): void
    {
        // Check game hasn't started
        if ($game->kickoff_at->isPast()) {
            throw ValidationException::withMessages([
                'game' => 'The game has already started. Predictions are closed.',
            ]);
        }

        // Check game is not finished
        if ($game->is_finished) {
            throw ValidationException::withMessages([
                'game' => 'The game has already been played.',
            ]);
        }

        // Validate goals are reasonable
        if ($data['goals_home'] < 0 || $data['goals_home'] > 20) {
            throw ValidationException::withMessages([
                'goals_home' => 'Goals must be between 0 and 20.',
            ]);
        }

        if ($data['goals_visitor'] < 0 || $data['goals_visitor'] > 20) {
            throw ValidationException::withMessages([
                'goals_visitor' => 'Goals must be between 0 and 20.',
            ]);
        }

        // For knockout games with draw, require penalty winner
        if ($game->isKnockout() &&
            $data['goals_home'] === $data['goals_visitor'] &&
            empty($data['penalty_winner_team_id'])) {
            throw ValidationException::withMessages([
                'penalty_winner' => 'For knockout games ending in a draw, you must predict the penalty shootout winner.',
            ]);
        }
    }
}
```

## Admin Game Result Entry

### Livewire Component

```php
<?php

namespace App\Livewire\Admin;

use App\Models\Game;
use App\Services\ScoringService;
use App\Services\GroupRankingService;
use Livewire\Component;

class EnterGameResult extends Component
{
    public Game $game;
    public ?int $goalsHome = null;
    public ?int $goalsVisitor = null;
    public ?int $goalsHomeHalftime = null;
    public ?int $goalsVisitorHalftime = null;
    public bool $hasPenaltyShootout = false;
    public ?int $penaltyWinner = null;

    public function mount(Game $game)
    {
        $this->game = $game->load(['homeTeam.nation', 'visitorTeam.nation']);

        if ($game->is_finished) {
            $this->goalsHome = $game->goals_home;
            $this->goalsVisitor = $game->goals_visitor;
            $this->goalsHomeHalftime = $game->goals_home_halftime;
            $this->goalsVisitorHalftime = $game->goals_visitor_halftime;
            $this->hasPenaltyShootout = $game->has_penalty_shootout;
            $this->penaltyWinner = $game->penalty_winner_team_id;
        }
    }

    public function saveResult(ScoringService $scoringService, GroupRankingService $groupRankingService)
    {
        $this->validate([
            'goalsHome' => 'required|integer|min:0|max:20',
            'goalsVisitor' => 'required|integer|min:0|max:20',
        ]);

        $this->game->update([
            'goals_home' => $this->goalsHome,
            'goals_visitor' => $this->goalsVisitor,
            'goals_home_halftime' => $this->goalsHomeHalftime,
            'goals_visitor_halftime' => $this->goalsVisitorHalftime,
            'has_penalty_shootout' => $this->hasPenaltyShootout,
            'penalty_winner_team_id' => $this->penaltyWinner,
            'is_finished' => true,
        ]);

        // Process all tipps and update scores
        $scoringService->processGameResult($this->game);

        // Update group standings if group game
        if ($this->game->isGroupGame()) {
            $groupRankingService->calculateGroupStandings(
                $this->game->group_name,
                $this->game->tournament_id
            );
        }

        session()->flash('message', 'Game result saved and scores updated.');
    }

    public function render()
    {
        return view('livewire.admin.enter-game-result');
    }
}
```

## Events for Score Updates

### GameResultEntered Event

```php
<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class GameResultEntered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Game $game) {}

    public function broadcastOn(): Channel
    {
        return new Channel('games');
    }
}
```

### Listener

```php
<?php

namespace App\Listeners;

use App\Events\GameResultEntered;
use App\Services\ScoringService;

class ProcessGameResult
{
    public function __construct(private ScoringService $scoringService) {}

    public function handle(GameResultEntered $event): void
    {
        $this->scoringService->processGameResult($event->game);
    }
}
```

## Testing Scoring Logic

```php
<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GameTipp;
use App\Services\ScoringService;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    private ScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScoringService();
    }

    public function test_exact_score_gives_4_points(): void
    {
        $game = Game::factory()->create([
            'goals_home' => 2,
            'goals_visitor' => 1,
            'is_finished' => true,
        ]);

        $tipp = GameTipp::factory()->create([
            'game_id' => $game->id,
            'goals_home' => 2,
            'goals_visitor' => 1,
        ]);

        $score = $this->service->calculateTippScore($tipp, $game);

        $this->assertEquals(4, $score);
        $this->assertTrue($tipp->is_goals_home_correct);
        $this->assertTrue($tipp->is_goals_visitor_correct);
    }

    public function test_goal_difference_gives_3_points(): void
    {
        $game = Game::factory()->create([
            'goals_home' => 3,
            'goals_visitor' => 2,
            'is_finished' => true,
        ]);

        $tipp = GameTipp::factory()->create([
            'game_id' => $game->id,
            'goals_home' => 2,
            'goals_visitor' => 1, // Same difference: +1
        ]);

        $score = $this->service->calculateTippScore($tipp, $game);

        $this->assertEquals(3, $score);
        $this->assertTrue($tipp->is_difference_correct);
    }

    public function test_tendency_gives_1_point(): void
    {
        $game = Game::factory()->create([
            'goals_home' => 3,
            'goals_visitor' => 0,
            'is_finished' => true,
        ]);

        $tipp = GameTipp::factory()->create([
            'game_id' => $game->id,
            'goals_home' => 1,
            'goals_visitor' => 0, // Home wins, but different score
        ]);

        $score = $this->service->calculateTippScore($tipp, $game);

        $this->assertEquals(1, $score);
        $this->assertTrue($tipp->is_tendency_correct);
    }

    public function test_wrong_tendency_gives_0_points(): void
    {
        $game = Game::factory()->create([
            'goals_home' => 0,
            'goals_visitor' => 1, // Away win
            'is_finished' => true,
        ]);

        $tipp = GameTipp::factory()->create([
            'game_id' => $game->id,
            'goals_home' => 2,
            'goals_visitor' => 0, // Home win predicted
        ]);

        $score = $this->service->calculateTippScore($tipp, $game);

        $this->assertEquals(0, $score);
        $this->assertFalse($tipp->is_tendency_correct);
    }
}
```
