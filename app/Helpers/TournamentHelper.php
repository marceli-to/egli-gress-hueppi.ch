<?php

namespace App\Helpers;

use App\Models\Tournament;
use Illuminate\Support\Facades\Cache;

class TournamentHelper
{
    public static function active(): ?Tournament
    {
        return Cache::remember('active_tournament', 3600, function () {
            return Tournament::where('is_active', true)->first();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('active_tournament');
    }
}
