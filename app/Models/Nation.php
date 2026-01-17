<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nation extends Model
{
    protected $fillable = [
        'code',
        'name',
        'fifa_ranking',
        'champion_count',
        'participation_count',
    ];

    protected function casts(): array
    {
        return [
            'fifa_ranking' => 'integer',
            'champion_count' => 'integer',
            'participation_count' => 'integer',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }
}
