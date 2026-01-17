<?php

use App\Models\Game;
use App\Models\Tournament;
use App\Services\ScoreCalculationService;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts.app')] class extends Component
{
    public string $filter = 'upcoming';
    public ?int $editingGameId = null;
    public ?int $goalsHome = null;
    public ?int $goalsVisitor = null;
    public ?int $goalsHomeHalftime = null;
    public ?int $goalsVisitorHalftime = null;
    public bool $hasPenaltyShootout = false;
    public ?int $penaltyWinnerTeamId = null;

    #[Computed]
    public function tournament()
    {
        return Tournament::where('is_active', true)->first();
    }

    #[Computed]
    public function games()
    {
        $query = Game::with(['homeTeam.nation', 'visitorTeam.nation', 'location'])
            ->where('tournament_id', $this->tournament?->id)
            ->orderBy('kickoff_at');

        return match ($this->filter) {
            'upcoming' => $query->where('is_finished', false)->get(),
            'finished' => $query->where('is_finished', true)->orderByDesc('kickoff_at')->get(),
            'today' => $query->whereDate('kickoff_at', today())->get(),
            default => $query->get(),
        };
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->cancelEdit();
    }

    public function editGame(int $gameId): void
    {
        $game = Game::find($gameId);
        if (!$game) return;

        $this->editingGameId = $gameId;
        $this->goalsHome = $game->goals_home;
        $this->goalsVisitor = $game->goals_visitor;
        $this->goalsHomeHalftime = $game->goals_home_halftime;
        $this->goalsVisitorHalftime = $game->goals_visitor_halftime;
        $this->hasPenaltyShootout = $game->has_penalty_shootout;
        $this->penaltyWinnerTeamId = $game->penalty_winner_team_id;
    }

    public function cancelEdit(): void
    {
        $this->editingGameId = null;
        $this->goalsHome = null;
        $this->goalsVisitor = null;
        $this->goalsHomeHalftime = null;
        $this->goalsVisitorHalftime = null;
        $this->hasPenaltyShootout = false;
        $this->penaltyWinnerTeamId = null;
    }

    public function saveResult(): void
    {
        $game = Game::find($this->editingGameId);
        if (!$game) {
            $this->cancelEdit();
            return;
        }

        $this->validate([
            'goalsHome' => 'required|integer|min:0|max:20',
            'goalsVisitor' => 'required|integer|min:0|max:20',
        ]);

        // For knockout games with draw, require penalty winner
        if ($game->isKnockoutGame() && $this->goalsHome === $this->goalsVisitor) {
            $this->validate([
                'penaltyWinnerTeamId' => 'required',
            ]);
            $this->hasPenaltyShootout = true;
        }

        $game->update([
            'goals_home' => $this->goalsHome,
            'goals_visitor' => $this->goalsVisitor,
            'goals_home_halftime' => $this->goalsHomeHalftime,
            'goals_visitor_halftime' => $this->goalsVisitorHalftime,
            'is_finished' => true,
            'has_penalty_shootout' => $this->hasPenaltyShootout,
            'penalty_winner_team_id' => $this->hasPenaltyShootout ? $this->penaltyWinnerTeamId : null,
        ]);

        // Calculate scores for all tipps
        $scoreService = new ScoreCalculationService();
        $scoreService->calculateGameScores($game);

        $this->cancelEdit();
    }

    public function reopenGame(int $gameId): void
    {
        $game = Game::find($gameId);
        if (!$game) return;

        $game->update([
            'is_finished' => false,
        ]);
    }

    public function getGameTypeLabel(string $type): string
    {
        return match ($type) {
            'GROUP' => 'Group',
            'ROUND_OF_16' => 'R16',
            'QUARTER_FINAL' => 'QF',
            'SEMI_FINAL' => 'SF',
            'THIRD_PLACE' => '3rd',
            'FINAL' => 'Final',
            default => $type,
        };
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Admin: Game Results') }}
            </h2>
            <span class="px-3 py-1 bg-red-100 text-red-800 text-sm rounded-full">Admin</span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filter Tabs -->
            <div class="mb-6 bg-white rounded-lg shadow-sm p-1 inline-flex gap-1">
                @foreach (['today' => 'Today', 'upcoming' => 'Upcoming', 'finished' => 'Finished', 'all' => 'All'] as $key => $label)
                    <button
                        wire:click="setFilter('{{ $key }}')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $filter === $key ? 'bg-red-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <!-- Games List -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Home</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Result</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Away</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($this->games as $game)
                            @if ($editingGameId === $game->id)
                                <tr class="bg-yellow-50">
                                    <td colspan="7" class="px-4 py-4">
                                        <form wire:submit="saveResult" class="space-y-4">
                                            <div class="flex items-center justify-center gap-4">
                                                <div class="text-right flex-1">
                                                    <span class="font-medium">{{ $game->homeTeam?->nation->name ?? $game->home_team_placeholder }}</span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <input type="number" wire:model="goalsHome" min="0" max="20" class="w-16 text-center rounded-md border-gray-300">
                                                    <span class="text-gray-400 font-bold">:</span>
                                                    <input type="number" wire:model="goalsVisitor" min="0" max="20" class="w-16 text-center rounded-md border-gray-300">
                                                </div>
                                                <div class="text-left flex-1">
                                                    <span class="font-medium">{{ $game->visitorTeam?->nation->name ?? $game->visitor_team_placeholder }}</span>
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-center gap-4 text-sm">
                                                <label class="text-gray-500">Halftime:</label>
                                                <input type="number" wire:model="goalsHomeHalftime" min="0" max="20" class="w-14 text-center rounded-md border-gray-300 text-sm" placeholder="H">
                                                <span class="text-gray-400">:</span>
                                                <input type="number" wire:model="goalsVisitorHalftime" min="0" max="20" class="w-14 text-center rounded-md border-gray-300 text-sm" placeholder="A">
                                            </div>

                                            @if ($game->isKnockoutGame())
                                                <div class="flex items-center justify-center gap-4">
                                                    <label class="flex items-center gap-2">
                                                        <input type="checkbox" wire:model.live="hasPenaltyShootout" class="rounded text-red-600">
                                                        <span class="text-sm text-gray-600">Penalty Shootout</span>
                                                    </label>
                                                </div>

                                                @if ($hasPenaltyShootout || ($goalsHome !== null && $goalsVisitor !== null && $goalsHome === $goalsVisitor))
                                                    <div class="flex items-center justify-center gap-4">
                                                        <span class="text-sm text-gray-600">Winner:</span>
                                                        @if ($game->homeTeam)
                                                            <label class="flex items-center gap-2">
                                                                <input type="radio" wire:model="penaltyWinnerTeamId" value="{{ $game->home_team_id }}" class="text-red-600">
                                                                <span class="text-sm">{{ $game->homeTeam->nation->name }}</span>
                                                            </label>
                                                        @endif
                                                        @if ($game->visitorTeam)
                                                            <label class="flex items-center gap-2">
                                                                <input type="radio" wire:model="penaltyWinnerTeamId" value="{{ $game->visitor_team_id }}" class="text-red-600">
                                                                <span class="text-sm">{{ $game->visitorTeam->nation->name }}</span>
                                                            </label>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endif

                                            @error('goalsHome') <p class="text-red-500 text-sm text-center">{{ $message }}</p> @enderror
                                            @error('goalsVisitor') <p class="text-red-500 text-sm text-center">{{ $message }}</p> @enderror
                                            @error('penaltyWinnerTeamId') <p class="text-red-500 text-sm text-center">{{ $message }}</p> @enderror

                                            <div class="flex justify-center gap-2">
                                                <button type="button" wire:click="cancelEdit" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                                                    Cancel
                                                </button>
                                                <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm rounded-md hover:bg-red-700">
                                                    Save Result
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @else
                                <tr class="{{ $game->is_finished ? 'bg-green-50' : '' }}">
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ $game->kickoff_at->format('d.m H:i') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 text-xs rounded {{ $game->game_type === 'GROUP' ? 'bg-gray-100' : 'bg-purple-100 text-purple-800' }}">
                                            {{ $this->getGameTypeLabel($game->game_type) }}
                                            @if ($game->group_name) {{ $game->group_name }} @endif
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-sm">
                                        {{ $game->homeTeam?->nation->name ?? $game->home_team_placeholder }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if ($game->is_finished)
                                            <span class="font-bold">{{ $game->goals_home }} : {{ $game->goals_visitor }}</span>
                                            @if ($game->has_penalty_shootout)
                                                <span class="text-xs text-gray-500">(P)</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">- : -</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium text-sm text-right">
                                        {{ $game->visitorTeam?->nation->name ?? $game->visitor_team_placeholder }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if ($game->is_finished)
                                            <span class="px-2 py-0.5 text-xs bg-green-100 text-green-800 rounded">Done</span>
                                        @elseif ($game->kickoff_at->isPast())
                                            <span class="px-2 py-0.5 text-xs bg-yellow-100 text-yellow-800 rounded">Live?</span>
                                        @else
                                            <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">Upcoming</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($game->is_finished)
                                            <button wire:click="editGame({{ $game->id }})" class="text-sm text-gray-500 hover:text-gray-700 mr-2">
                                                Edit
                                            </button>
                                            <button wire:click="reopenGame({{ $game->id }})" wire:confirm="Reopen this game? Scores will need to be recalculated." class="text-sm text-red-500 hover:text-red-700">
                                                Reopen
                                            </button>
                                        @else
                                            <button wire:click="editGame({{ $game->id }})" class="text-sm text-red-600 hover:text-red-800 font-medium">
                                                Enter Result
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    No games found for this filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
