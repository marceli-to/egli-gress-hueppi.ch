<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = [
        'tournament_id',
        'nation_id',
        'group_name',
        'points',
        'goals_for',
        'goals_against',
        'wins',
        'draws',
        'losses',
        'fair_play_points',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'goals_for' => 'integer',
            'goals_against' => 'integer',
            'wins' => 'integer',
            'draws' => 'integer',
            'losses' => 'integer',
            'fair_play_points' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function nation(): BelongsTo
    {
        return $this->belongsTo(Nation::class);
    }

    public function homeGames(): HasMany
    {
        return $this->hasMany(Game::class, 'home_team_id');
    }

    public function visitorGames(): HasMany
    {
        return $this->hasMany(Game::class, 'visitor_team_id');
    }

    public function goalDifference(): int
    {
        return $this->goals_for - $this->goals_against;
    }
}
