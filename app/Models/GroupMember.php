<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMember extends Model
{
    protected $fillable = [
        'tipp_group_id',
        'user_id',
        'rank',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
        ];
    }

    public function tippGroup(): BelongsTo
    {
        return $this->belongsTo(TippGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
