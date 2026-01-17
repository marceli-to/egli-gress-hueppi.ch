<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpecialTippSpec extends Model
{
    protected $fillable = [
        'tournament_id',
        'name',
        'type',
        'value',
        'team_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function tipps(): HasMany
    {
        return $this->hasMany(SpecialTipp::class);
    }
}
