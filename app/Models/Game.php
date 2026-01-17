<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'tournament_id',
        'game_type',
        'group_name',
        'kickoff_at',
        'location_id',
        'home_team_id',
        'visitor_team_id',
        'home_team_placeholder',
        'visitor_team_placeholder',
        'goals_home',
        'goals_visitor',
        'goals_home_halftime',
        'goals_visitor_halftime',
        'is_finished',
        'has_penalty_shootout',
        'penalty_winner_team_id',
    ];

    protected function casts(): array
    {
        return [
            'kickoff_at' => 'datetime',
            'goals_home' => 'integer',
            'goals_visitor' => 'integer',
            'goals_home_halftime' => 'integer',
            'goals_visitor_halftime' => 'integer',
            'is_finished' => 'boolean',
            'has_penalty_shootout' => 'boolean',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function visitorTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'visitor_team_id');
    }

    public function penaltyWinnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'penalty_winner_team_id');
    }

    public function tipps(): HasMany
    {
        return $this->hasMany(GameTipp::class);
    }

    public function isGroupGame(): bool
    {
        return $this->game_type === 'GROUP';
    }

    public function isKnockoutGame(): bool
    {
        return !$this->isGroupGame();
    }

    public function isDraw(): bool
    {
        return $this->is_finished && $this->goals_home === $this->goals_visitor;
    }

    public function canTipp(): bool
    {
        return !$this->is_finished && $this->kickoff_at->isFuture();
    }
}
