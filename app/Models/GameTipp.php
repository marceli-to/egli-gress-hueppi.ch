<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameTipp extends Model
{
    protected $fillable = [
        'user_id',
        'game_id',
        'goals_home',
        'goals_visitor',
        'penalty_winner_team_id',
    ];

    protected function casts(): array
    {
        return [
            'goals_home' => 'integer',
            'goals_visitor' => 'integer',
            'score' => 'integer',
            'is_tendency_correct' => 'boolean',
            'is_difference_correct' => 'boolean',
            'is_goals_home_correct' => 'boolean',
            'is_goals_visitor_correct' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function penaltyWinnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'penalty_winner_team_id');
    }

    public function predictsDraw(): bool
    {
        return $this->goals_home === $this->goals_visitor;
    }

    public function predictsHomeWin(): bool
    {
        return $this->goals_home > $this->goals_visitor;
    }

    public function predictsVisitorWin(): bool
    {
        return $this->goals_visitor > $this->goals_home;
    }
}
