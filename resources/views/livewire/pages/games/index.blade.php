<?php

use App\Models\Game;
use App\Models\GameTipp;
use App\Models\Tournament;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts.app')] class extends Component
{
    public string $filter = 'upcoming';
    public ?int $editingGameId = null;
    public ?int $goalsHome = null;
    public ?int $goalsVisitor = null;
    public ?int $penaltyWinner = null;

    #[Computed]
    public function tournament()
    {
        return Tournament::where('is_active', true)->first();
    }

    #[Computed]
    public function games()
    {
        $query = Game::with(['homeTeam.nation', 'visitorTeam.nation', 'location', 'tipps' => function ($q) {
            $q->where('user_id', auth()->id());
        }])
            ->where('tournament_id', $this->tournament?->id)
            ->orderBy('kickoff_at');

        return match ($this->filter) {
            'upcoming' => $query->where('is_finished', false)->get(),
            'finished' => $query->where('is_finished', true)->orderByDesc('kickoff_at')->get(),
            'group' => $query->where('game_type', 'GROUP')->get(),
            'knockout' => $query->where('game_type', '!=', 'GROUP')->get(),
            default => $query->get(),
        };
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->editingGameId = null;
    }

    public function editTipp(int $gameId): void
    {
        $game = Game::with(['tipps' => fn($q) => $q->where('user_id', auth()->id())])->find($gameId);

        if (!$game || !$game->canTipp()) {
            return;
        }

        $this->editingGameId = $gameId;
        $existingTipp = $game->tipps->first();

        if ($existingTipp) {
            $this->goalsHome = $existingTipp->goals_home;
            $this->goalsVisitor = $existingTipp->goals_visitor;
            $this->penaltyWinner = $existingTipp->penalty_winner_team_id;
        } else {
            $this->goalsHome = null;
            $this->goalsVisitor = null;
            $this->penaltyWinner = null;
        }
    }

    public function cancelEdit(): void
    {
        $this->editingGameId = null;
        $this->goalsHome = null;
        $this->goalsVisitor = null;
        $this->penaltyWinner = null;
    }

    public function saveTipp(): void
    {
        $game = Game::find($this->editingGameId);

        if (!$game || !$game->canTipp()) {
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
                'penaltyWinner' => 'required|in:' . $game->home_team_id . ',' . $game->visitor_team_id,
            ]);
        }

        GameTipp::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'game_id' => $game->id,
            ],
            [
                'goals_home' => $this->goalsHome,
                'goals_visitor' => $this->goalsVisitor,
                'penalty_winner_team_id' => $game->isKnockoutGame() && $this->goalsHome === $this->goalsVisitor
                    ? $this->penaltyWinner
                    : null,
            ]
        );

        $this->cancelEdit();
    }

    public function getGameTypeLabel(string $type): string
    {
        return match ($type) {
            'GROUP' => 'Group Stage',
            'ROUND_OF_16' => 'Round of 16',
            'QUARTER_FINAL' => 'Quarter Final',
            'SEMI_FINAL' => 'Semi Final',
            'THIRD_PLACE' => 'Third Place',
            'FINAL' => 'Final',
            default => $type,
        };
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Games') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filter Tabs -->
            <div class="mb-6 bg-white rounded-lg shadow-sm p-1 inline-flex gap-1">
                @foreach (['upcoming' => 'Upcoming', 'finished' => 'Finished', 'group' => 'Group Stage', 'knockout' => 'Knockout', 'all' => 'All'] as $key => $label)
                    <button
                        wire:click="setFilter('{{ $key }}')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $filter === $key ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <!-- Games List -->
            <div class="space-y-4">
                @forelse ($this->games as $game)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <!-- Game Header -->
                            <div class="flex items-center justify-between mb-4">
                                <div class="text-sm text-gray-500">
                                    <span class="font-medium">{{ $this->getGameTypeLabel($game->game_type) }}</span>
                                    @if ($game->group_name)
                                        <span class="ml-2 px-2 py-0.5 bg-gray-100 rounded">Group {{ $game->group_name }}</span>
                                    @endif
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $game->kickoff_at->format('D, d M Y - H:i') }}
                                </div>
                            </div>

                            <!-- Teams and Score -->
                            <div class="flex items-center justify-center gap-4">
                                <!-- Home Team -->
                                <div class="flex-1 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <span class="font-semibold text-lg">
                                            {{ $game->homeTeam?->nation->name ?? $game->home_team_placeholder }}
                                        </span>
                                        @if ($game->homeTeam)
                                            <x-flag :code="$game->homeTeam->nation->code" />
                                        @endif
                                    </div>
                                </div>

                                <!-- Score -->
                                <div class="flex items-center gap-2 px-6">
                                    @if ($game->is_finished)
                                        <span class="text-3xl font-bold">{{ $game->goals_home }}</span>
                                        <span class="text-xl text-gray-400">:</span>
                                        <span class="text-3xl font-bold">{{ $game->goals_visitor }}</span>
                                        @if ($game->has_penalty_shootout)
                                            <span class="text-xs text-gray-500 ml-2">(P)</span>
                                        @endif
                                    @else
                                        <span class="text-xl text-gray-400">vs</span>
                                    @endif
                                </div>

                                <!-- Visitor Team -->
                                <div class="flex-1 text-left">
                                    <div class="flex items-center gap-3">
                                        @if ($game->visitorTeam)
                                            <x-flag :code="$game->visitorTeam->nation->code" />
                                        @endif
                                        <span class="font-semibold text-lg">
                                            {{ $game->visitorTeam?->nation->name ?? $game->visitor_team_placeholder }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- User's Tipp -->
                            @php $userTipp = $game->tipps->first(); @endphp

                            @if ($editingGameId === $game->id)
                                <!-- Edit Form -->
                                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                                    <form wire:submit="saveTipp" class="space-y-4">
                                        <div class="flex items-center justify-center gap-4">
                                            <div class="text-right flex-1">
                                                <span class="text-sm text-gray-600">{{ $game->homeTeam?->nation->name ?? $game->home_team_placeholder }}</span>
                                            </div>
                                            <input
                                                type="number"
                                                wire:model="goalsHome"
                                                min="0"
                                                max="20"
                                                class="w-16 text-center rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                            <span class="text-gray-400">:</span>
                                            <input
                                                type="number"
                                                wire:model="goalsVisitor"
                                                min="0"
                                                max="20"
                                                class="w-16 text-center rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                            <div class="text-left flex-1">
                                                <span class="text-sm text-gray-600">{{ $game->visitorTeam?->nation->name ?? $game->visitor_team_placeholder }}</span>
                                            </div>
                                        </div>

                                        @if ($game->isKnockoutGame() && $goalsHome !== null && $goalsVisitor !== null && $goalsHome === $goalsVisitor)
                                            <div class="text-center">
                                                <p class="text-sm text-gray-600 mb-2">Penalty shootout winner:</p>
                                                <div class="flex justify-center gap-4">
                                                    @if ($game->homeTeam)
                                                        <label class="flex items-center gap-2">
                                                            <input type="radio" wire:model="penaltyWinner" value="{{ $game->home_team_id }}" class="text-indigo-600">
                                                            <span>{{ $game->homeTeam->nation->name }}</span>
                                                        </label>
                                                    @endif
                                                    @if ($game->visitorTeam)
                                                        <label class="flex items-center gap-2">
                                                            <input type="radio" wire:model="penaltyWinner" value="{{ $game->visitor_team_id }}" class="text-indigo-600">
                                                            <span>{{ $game->visitorTeam->nation->name }}</span>
                                                        </label>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif

                                        @error('goalsHome') <p class="text-red-500 text-sm text-center">{{ $message }}</p> @enderror
                                        @error('goalsVisitor') <p class="text-red-500 text-sm text-center">{{ $message }}</p> @enderror
                                        @error('penaltyWinner') <p class="text-red-500 text-sm text-center">{{ $message }}</p> @enderror

                                        <div class="flex justify-center gap-2">
                                            <button type="button" wire:click="cancelEdit" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                                                Cancel
                                            </button>
                                            <button type="submit" class="px-4 py-2 bg-gray-900 text-white text-sm rounded-md hover:bg-gray-800">
                                                Save Tipp
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            @else
                                <div class="mt-4 pt-4 border-t border-gray-100">
                                    @if ($userTipp)
                                        <div class="flex items-center justify-between">
                                            <div class="text-sm">
                                                <span class="text-gray-500">Your tipp:</span>
                                                <span class="ml-2 font-semibold">{{ $userTipp->goals_home }} : {{ $userTipp->goals_visitor }}</span>
                                                @if ($userTipp->penalty_winner_team_id)
                                                    <span class="text-gray-500 text-xs ml-1">(P: {{ $userTipp->penaltyWinnerTeam?->nation->name }})</span>
                                                @endif
                                            </div>
                                            @if ($game->is_finished)
                                                <div class="flex items-center gap-2">
                                                    <span class="px-2 py-1 text-sm rounded {{ $userTipp->score > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                                        +{{ $userTipp->score }} points
                                                    </span>
                                                </div>
                                            @elseif ($game->canTipp())
                                                <button wire:click="editTipp({{ $game->id }})" class="text-sm text-indigo-600 hover:text-indigo-800">
                                                    Edit
                                                </button>
                                            @endif
                                        </div>
                                    @elseif ($game->canTipp())
                                        <button wire:click="editTipp({{ $game->id }})" class="w-full py-2 text-sm text-indigo-600 hover:text-indigo-800 border border-dashed border-gray-300 rounded-md hover:border-indigo-300">
                                            + Add your tipp
                                        </button>
                                    @else
                                        <p class="text-sm text-gray-400 text-center">Tipp deadline passed</p>
                                    @endif
                                </div>
                            @endif

                            <!-- Location -->
                            <div class="mt-4 text-center text-xs text-gray-400">
                                {{ $game->location->name }}, {{ $game->location->city }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                        No games found for this filter.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
