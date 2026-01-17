<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialTipp extends Model
{
    protected $fillable = [
        'user_id',
        'special_tipp_spec_id',
        'predicted_team_id',
        'predicted_value',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'predicted_value' => 'integer',
            'score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialTippSpec(): BelongsTo
    {
        return $this->belongsTo(SpecialTippSpec::class);
    }

    public function predictedTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'predicted_team_id');
    }
}
