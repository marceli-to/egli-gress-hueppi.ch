<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $guarded = [
        'id',
        'is_admin',
        'is_simulated',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_simulated' => 'boolean',
        ];
    }

    public function gameTipps(): HasMany
    {
        return $this->hasMany(GameTipp::class);
    }

    public function specialTipps(): HasMany
    {
        return $this->hasMany(SpecialTipp::class);
    }

    public function userScores(): HasMany
    {
        return $this->hasMany(UserScore::class);
    }

    public function userScoreHistories(): HasMany
    {
        return $this->hasMany(UserScoreHistory::class);
    }

    public function ownedTippGroups(): HasMany
    {
        return $this->hasMany(TippGroup::class, 'owner_user_id');
    }

    public function tippGroups(): BelongsToMany
    {
        return $this->belongsToMany(TippGroup::class, 'group_members')
            ->withPivot('rank')
            ->withTimestamps();
    }

    public function displayName(): string
    {
        return $this->display_name ?? $this->name;
    }
}
