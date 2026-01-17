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
    }

    /**
     * Calculate score for a single tipp
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
        $isExactMatch = $isGoalsHomeCorrect && $isGoalsVisitorCorrect;

        // Goal difference check (only for non-draws)
        $actualDiff = $actualHome - $actualVisitor;
        $tippedDiff = $tippedHome - $tippedVisitor;
        $isDifferenceCorrect = $actualDiff === $tippedDiff;

        // Tendency check
        $actualTendency = $actualHome <=> $actualVisitor; // -1, 0, or 1
        $tippedTendency = $tippedHome <=> $tippedVisitor;
        $isTendencyCorrect = $actualTendency === $tippedTendency;

        // Calculate score
        $score = 0;
        if ($isExactMatch) {
            $score = 4;
        } elseif ($isDifferenceCorrect && $isTendencyCorrect) {
            $score = 3;
        } elseif ($isTendencyCorrect) {
            $score = 1;
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
        $specs = SpecialTippSpec::where('tournament_id', $tournamentId)
            ->whereNotNull('team_id')
            ->get();

        foreach ($specs as $spec) {
            $tipps = SpecialTipp::where('special_tipp_spec_id', $spec->id)->get();

            foreach ($tipps as $tipp) {
                $isCorrect = $tipp->predicted_team_id === $spec->team_id;
                $tipp->update([
                    'score' => $isCorrect ? $spec->value : 0,
                ]);
            }
        }

        $this->recalculateUserScores($tournamentId);
    }
}
