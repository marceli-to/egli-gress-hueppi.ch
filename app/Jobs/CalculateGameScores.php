<?php

namespace App\Jobs;

use App\Models\Game;
use App\Services\ScoreCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateGameScores implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Game $game
    ) {}

    public function handle(ScoreCalculationService $service): void
    {
        $service->calculateGameScores($this->game);
    }
}
