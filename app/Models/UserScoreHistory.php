<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserScoreHistory extends Model
{
    protected $fillable = [
        'user_id',
        'tournament_id',
        'game_day',
        'points',
        'rank',
        'rank_delta',
    ];

    protected function casts(): array
    {
        return [
            'game_day' => 'integer',
            'points' => 'integer',
            'rank' => 'integer',
            'rank_delta' => 'integer',
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
}
