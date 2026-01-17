<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserScore extends Model
{
    protected $fillable = [
        'user_id',
        'tournament_id',
        'total_points',
        'game_points',
        'special_points',
        'rank',
        'rank_delta',
        'tipp_count',
        'average_score',
        'champion_team_id',
    ];

    protected function casts(): array
    {
        return [
            'total_points' => 'integer',
            'game_points' => 'integer',
            'special_points' => 'integer',
            'rank' => 'integer',
            'rank_delta' => 'integer',
            'tipp_count' => 'integer',
            'average_score' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function championTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'champion_team_id');
    }
}
