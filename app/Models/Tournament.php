<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'is_complete',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_complete' => 'boolean',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function specialTippSpecs(): HasMany
    {
        return $this->hasMany(SpecialTippSpec::class);
    }

    public function tippGroups(): HasMany
    {
        return $this->hasMany(TippGroup::class);
    }

    public function userScores(): HasMany
    {
        return $this->hasMany(UserScore::class);
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }
}
