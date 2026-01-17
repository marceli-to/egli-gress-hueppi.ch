<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameTipp;
use App\Models\SpecialTipp;
use App\Models\SpecialTippSpec;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Models\UserScore;
use App\Models\UserScoreHistory;
use Illuminate\Support\Facades\DB;

class ScoreCalculationService
{
    /**
     * Calculate and update scores for all tipps of a finished game
     */
    public function calculateGameScores(Game $game): void
    {
        if (!$game->is_finished || $game->goals_home === null || $game->goals_visitor === null) {
            return;
        }

        DB::transaction(function () use ($game) {
            $tipps = GameTipp::where('game_id', $game->id)->get();

            foreach ($tipps as $tipp) {
                $this->calculateTippScore($tipp, $game);
            }

            // Update team stats for group games
            if ($game->isGroupGame()) {
                $this->updateTeamStats($game);
            }

            // Recalculate user scores for this tournament
            $this->recalculateUserScores($game->tournament_id);

            // Track history
            $this->trackScoreHistory($game);
        });
    }

    /**
     * Calculate score for a single tipp using cumulative scoring
     *
     * Group Phase (max 10 points):
     * - 5 points: Correct tendency (win/draw/loss)
     * - 3 points: Correct goal difference
     * - 1 point: Correct home team goals
     * - 1 point: Correct visitor team goals
     *
     * K.O. Phase (max 20 points):
     * - 10 points: Correct winner (team that advances, including penalty shootout)
     * - 3 points: Correct tendency after 90/120 min
     * - 3 points: Correct goal difference
     * - 2 points: Correct home team goals
     * - 2 points: Correct visitor team goals
     */
    public function calculateTippScore(GameTipp $tipp, Game $game): void
    {
        $actualHome = $game->goals_home;
        $actualVisitor = $game->goals_visitor;
        $tippedHome = $tipp->goals_home;
        $tippedVisitor = $tipp->goals_visitor;

        // Check individual conditions
        $isGoalsHomeCorrect = $tippedHome === $actualHome;
        $isGoalsVisitorCorrect = $tippedVisitor === $actualVisitor;

        // Goal difference check
        $actualDiff = $actualHome - $actualVisitor;
        $tippedDiff = $tippedHome - $tippedVisitor;
        $isDifferenceCorrect = $actualDiff === $tippedDiff;

        // Tendency check (after 90/120 min, before penalties)
        $actualTendency = $actualHome <=> $actualVisitor; // -1, 0, or 1
        $tippedTendency = $tippedHome <=> $tippedVisitor;
        $isTendencyCorrect = $actualTendency === $tippedTendency;

        // Calculate cumulative score
        $score = 0;

        if ($game->isKnockoutGame()) {
            // K.O. Phase scoring (max 20 points)

            // Check if winner prediction is correct (10 points)
            // For knockout games, we need to check who advances
            $actualWinnerId = $this->getKnockoutWinner($game);
            $predictedWinnerId = $this->getPredictedKnockoutWinner($tipp, $game);
            $isWinnerCorrect = $actualWinnerId && $predictedWinnerId && $actualWinnerId === $predictedWinnerId;

            if ($isWinnerCorrect) {
                $score += 10;
            }

            if ($isTendencyCorrect) {
                $score += 3; // Correct tendency after 90/120 min
            }

            if ($isDifferenceCorrect) {
                $score += 3; // Correct goal difference
            }

            if ($isGoalsHomeCorrect) {
                $score += 2; // Correct home goals
            }

            if ($isGoalsVisitorCorrect) {
                $score += 2; // Correct visitor goals
            }
        } else {
            // Group Phase scoring (max 10 points)

            if ($isTendencyCorrect) {
                $score += 5; // Correct tendency
            }

            if ($isDifferenceCorrect) {
                $score += 3; // Correct goal difference
            }

            if ($isGoalsHomeCorrect) {
                $score += 1; // Correct home goals
            }

            if ($isGoalsVisitorCorrect) {
                $score += 1; // Correct visitor goals
            }
        }

        $tipp->update([
            'score' => $score,
            'is_tendency_correct' => $isTendencyCorrect,
            'is_difference_correct' => $isDifferenceCorrect,
            'is_goals_home_correct' => $isGoalsHomeCorrect,
            'is_goals_visitor_correct' => $isGoalsVisitorCorrect,
        ]);
    }

    /**
     * Get the actual winner of a knockout game (the team that advances)
     */
    private function getKnockoutWinner(Game $game): ?int
    {
        if ($game->goals_home > $game->goals_visitor) {
            return $game->home_team_id;
        } elseif ($game->goals_visitor > $game->goals_home) {
            return $game->visitor_team_id;
        } elseif ($game->has_penalty_shootout && $game->penalty_winner_team_id) {
            return $game->penalty_winner_team_id;
        }
        return null;
    }

    /**
     * Get the predicted winner of a knockout game from a tipp
     */
    private function getPredictedKnockoutWinner(GameTipp $tipp, Game $game): ?int
    {
        if ($tipp->goals_home > $tipp->goals_visitor) {
            return $game->home_team_id;
        } elseif ($tipp->goals_visitor > $tipp->goals_home) {
            return $game->visitor_team_id;
        } elseif ($tipp->penalty_winner_team_id) {
            return $tipp->penalty_winner_team_id;
        }
        return null;
    }

    /**
     * Update team statistics after a group game
     */
    public function updateTeamStats(Game $game): void
    {
        if (!$game->isGroupGame() || !$game->homeTeam || !$game->visitorTeam) {
            return;
        }

        $homeTeam = $game->homeTeam;
        $visitorTeam = $game->visitorTeam;

        $goalsHome = $game->goals_home;
        $goalsVisitor = $game->goals_visitor;

        // Update goals
        $homeTeam->goals_for += $goalsHome;
        $homeTeam->goals_against += $goalsVisitor;
        $visitorTeam->goals_for += $goalsVisitor;
        $visitorTeam->goals_against += $goalsHome;

        // Update wins/draws/losses and points
        if ($goalsHome > $goalsVisitor) {
            $homeTeam->wins += 1;
            $homeTeam->points += 3;
            $visitorTeam->losses += 1;
        } elseif ($goalsHome < $goalsVisitor) {
            $visitorTeam->wins += 1;
            $visitorTeam->points += 3;
            $homeTeam->losses += 1;
        } else {
            $homeTeam->draws += 1;
            $homeTeam->points += 1;
            $visitorTeam->draws += 1;
            $visitorTeam->points += 1;
        }

        $homeTeam->save();
        $visitorTeam->save();
    }

    /**
     * Recalculate all user scores for a tournament
     */
    public function recalculateUserScores(int $tournamentId): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $this->calculateUserScore($user, $tournamentId);
        }

        // Update rankings
        $this->updateRankings($tournamentId);
    }

    /**
     * Calculate total score for a user in a tournament
     */
    public function calculateUserScore(User $user, int $tournamentId): void
    {
        // Game points
        $gamePoints = GameTipp::where('user_id', $user->id)
            ->whereHas('game', fn($q) => $q->where('tournament_id', $tournamentId)->where('is_finished', true))
            ->sum('score');

        // Tipp count
        $tippCount = GameTipp::where('user_id', $user->id)
            ->whereHas('game', fn($q) => $q->where('tournament_id', $tournamentId))
            ->count();

        // Special points
        $specialPoints = SpecialTipp::where('user_id', $user->id)
            ->whereHas('specialTippSpec', fn($q) => $q->where('tournament_id', $tournamentId))
            ->sum('score');

        // Get champion prediction
        $championSpec = SpecialTippSpec::where('tournament_id', $tournamentId)
            ->where('name', 'WINNER_WORLDCUP')
            ->first();

        $championTeamId = null;
        if ($championSpec) {
            $championTipp = SpecialTipp::where('user_id', $user->id)
                ->where('special_tipp_spec_id', $championSpec->id)
                ->first();
            $championTeamId = $championTipp?->predicted_team_id;
        }

        $totalPoints = $gamePoints + $specialPoints;
        $averageScore = $tippCount > 0 ? round($totalPoints / $tippCount, 2) : 0;

        UserScore::updateOrCreate(
            [
                'user_id' => $user->id,
                'tournament_id' => $tournamentId,
            ],
            [
                'total_points' => $totalPoints,
                'game_points' => $gamePoints,
                'special_points' => $specialPoints,
                'tipp_count' => $tippCount,
                'average_score' => $averageScore,
                'champion_team_id' => $championTeamId,
            ]
        );
    }

    /**
     * Update rankings for all users in a tournament
     */
    public function updateRankings(int $tournamentId): void
    {
        $scores = UserScore::where('tournament_id', $tournamentId)
            ->orderByDesc('total_points')
            ->orderByDesc('game_points')
            ->orderByDesc('average_score')
            ->get();

        $rank = 0;
        $lastPoints = null;
        $lastGamePoints = null;

        foreach ($scores as $index => $score) {
            // Handle ties - same rank for same points
            if ($score->total_points !== $lastPoints || $score->game_points !== $lastGamePoints) {
                $rank = $index + 1;
            }

            $previousRank = $score->rank;
            $rankDelta = $previousRank > 0 ? $previousRank - $rank : 0;

            $score->update([
                'rank' => $rank,
                'rank_delta' => $rankDelta,
            ]);

            $lastPoints = $score->total_points;
            $lastGamePoints = $score->game_points;
        }
    }

    /**
     * Track score history after a game
     */
    public function trackScoreHistory(Game $game): void
    {
        // Calculate game day (number of finished games)
        $gameDay = Game::where('tournament_id', $game->tournament_id)
            ->where('is_finished', true)
            ->count();

        $userScores = UserScore::where('tournament_id', $game->tournament_id)->get();

        foreach ($userScores as $userScore) {
            // Get previous history entry
            $previousHistory = UserScoreHistory::where('user_id', $userScore->user_id)
                ->where('tournament_id', $game->tournament_id)
                ->orderByDesc('game_day')
                ->first();

            $previousRank = $previousHistory?->rank ?? 0;
            $rankDelta = $previousRank > 0 ? $previousRank - $userScore->rank : 0;

            UserScoreHistory::updateOrCreate(
                [
                    'user_id' => $userScore->user_id,
                    'tournament_id' => $game->tournament_id,
                    'game_day' => $gameDay,
                ],
                [
                    'points' => $userScore->total_points,
                    'rank' => $userScore->rank,
                    'rank_delta' => $rankDelta,
                ]
            );
        }
    }

    /**
     * Calculate special tipp scores (called after tournament milestones)
     */
    public function calculateSpecialTippScores(int $tournamentId): void
    {
        DB::transaction(function () use ($tournamentId) {
            $specs = SpecialTippSpec::where('tournament_id', $tournamentId)->get();

            foreach ($specs as $spec) {
                $tipps = SpecialTipp::where('special_tipp_spec_id', $spec->id)->get();

                foreach ($tipps as $tipp) {
                    $score = $this->calculateSpecialTippScore($tipp, $spec);
                    $tipp->update(['score' => $score]);
                }
            }

            $this->recalculateUserScores($tournamentId);
        });
    }

    /**
     * Calculate score for a single special tipp
     */
    private function calculateSpecialTippScore(SpecialTipp $tipp, SpecialTippSpec $spec): int
    {
        if ($spec->type === 'WINNER') {
            // Winner type: compare predicted team with actual winner
            if ($spec->team_id && $tipp->predicted_team_id === $spec->team_id) {
                return $spec->value;
            }
        } elseif ($spec->type === 'TOTAL_GOALS') {
            // Total goals: compare predicted value with actual value
            if ($spec->result_value !== null && $tipp->predicted_value == $spec->result_value) {
                return $spec->value;
            }
        } elseif ($spec->type === 'FINAL_RANKING') {
            // Final ranking: compare predicted ranking with actual ranking
            if ($spec->result_ranking && $tipp->predicted_ranking === $spec->result_ranking) {
                return $spec->value;
            }
        }

        return 0;
    }
}
