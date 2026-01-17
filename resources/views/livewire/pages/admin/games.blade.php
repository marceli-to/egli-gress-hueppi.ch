<?php

use App\Models\Game;
use App\Services\ScoreCalculationService;
use App\Helpers\TournamentHelper;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

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
    public bool $isFinished = false;

    #[Computed]
    public function tournament()
    {
        return TournamentHelper::active();
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
        $this->isFinished = $game->is_finished;
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
        $this->isFinished = false;
    }

    public function saveResult(): void
    {
        if (!auth()->user()?->is_admin) {
            abort(403);
        }

        $this->validate([
            'goalsHome' => 'required|integer|min:0|max:20',
            'goalsVisitor' => 'required|integer|min:0|max:20',
            'goalsHomeHalftime' => 'nullable|integer|min:0|lte:goalsHome',
            'goalsVisitorHalftime' => 'nullable|integer|min:0|lte:goalsVisitor',
        ]);

        DB::transaction(function () {
            $game = Game::lockForUpdate()->find($this->editingGameId);

            if (!$game) {
                $this->cancelEdit();
                return;
            }

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
                'is_finished' => $this->isFinished,
                'has_penalty_shootout' => $this->hasPenaltyShootout,
                'penalty_winner_team_id' => $this->hasPenaltyShootout ? $this->penaltyWinnerTeamId : null,
            ]);

            // Calculate scores if finished
            if ($this->isFinished) {
                $scoreService = new ScoreCalculationService();
                $scoreService->calculateGameScores($game);
            }
        });

        $this->cancelEdit();
    }

    public function reopenGame(int $gameId): void
    {
        if (!auth()->user()?->is_admin) {
            abort(403);
        }

        $game = Game::find($gameId);
        if (!$game) return;

        $game->update([
            'is_finished' => false,
        ]);
    }

    public function getGameTypeLabel(string $type): string
    {
        return match ($type) {
            'GROUP' => 'Gruppe',
            'ROUND_OF_16' => 'AF',
            'QUARTER_FINAL' => 'VF',
            'SEMI_FINAL' => 'HF',
            'THIRD_PLACE' => '3. Platz',
            'FINAL' => 'Finale',
            default => $type,
        };
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Admin: Spielergebnisse') }}
            </h2>
            <span class="px-3 py-1 bg-red-100 text-red-800 text-sm rounded-full">Admin</span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filter Tabs -->
            <div class="mb-6 bg-white rounded-lg shadow-sm p-1 inline-flex gap-1">
                @foreach (['today' => 'Heute', 'upcoming' => 'Ausstehend', 'finished' => 'Beendet', 'all' => 'Alle'] as $key => $label)
                    <button
                        wire:click="setFilter('{{ $key }}')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $filter === $key ? 'bg-red-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <!-- Games List -->
            <div class="space-y-4">
                @forelse ($this->games as $game)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <!-- Game Header -->
                        <div class="px-4 py-2 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                            <div class="text-sm text-gray-500 uppercase">
                                {{ $game->kickoff_at->format('D, d.m.Y - H:i') }} - {{ $game->location->city }}
                            </div>
                            <span class="px-2 py-0.5 text-xs rounded {{ $game->game_type === 'GROUP' ? 'bg-gray-200' : 'bg-purple-100 text-purple-800' }}">
                                {{ $this->getGameTypeLabel($game->game_type) }}
                                @if ($game->group_name) {{ $game->group_name }} @endif
                            </span>
                        </div>

                        @if ($editingGameId === $game->id)
                            <!-- Edit Form -->
                            <div class="p-6">
                                <form wire:submit="saveResult" class="space-y-6">
                                    <!-- Teams and Score -->
                                    <div class="flex items-center justify-center gap-6">
                                        <div class="text-right flex-1">
                                            <div class="flex items-center justify-end gap-2">
                                                <span class="font-semibold">{{ $game->homeTeam?->nation->name ?? $game->home_team_placeholder }}</span>
                                                @if ($game->homeTeam)
                                                    <x-flag :code="$game->homeTeam->nation->code" />
                                                @endif
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <div class="text-center">
                                                <label class="text-xs text-gray-500 block mb-1">Tore</label>
                                                <input type="number" wire:model="goalsHome" min="0" max="20" class="w-16 text-center text-xl font-bold rounded-md border-gray-300">
                                            </div>
                                            <span class="text-2xl text-gray-400 mt-5">:</span>
                                            <div class="text-center">
                                                <label class="text-xs text-gray-500 block mb-1">Tore</label>
                                                <input type="number" wire:model="goalsVisitor" min="0" max="20" class="w-16 text-center text-xl font-bold rounded-md border-gray-300">
                                            </div>
                                        </div>

                                        <div class="text-left flex-1">
                                            <div class="flex items-center gap-2">
                                                @if ($game->visitorTeam)
                                                    <x-flag :code="$game->visitorTeam->nation->code" />
                                                @endif
                                                <span class="font-semibold">{{ $game->visitorTeam?->nation->name ?? $game->visitor_team_placeholder }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Halftime -->
                                    <div class="flex items-center justify-center gap-4 text-sm">
                                        <span class="text-gray-500">Halbzeit:</span>
                                        <input type="number" wire:model="goalsHomeHalftime" min="0" max="20" class="w-14 text-center rounded-md border-gray-300 text-sm">
                                        <span class="text-gray-400">:</span>
                                        <input type="number" wire:model="goalsVisitorHalftime" min="0" max="20" class="w-14 text-center rounded-md border-gray-300 text-sm">
                                    </div>

                                    @if ($game->isKnockoutGame())
                                        <!-- Penalty Shootout -->
                                        <div class="flex items-center justify-center gap-6">
                                            <label class="flex items-center gap-2">
                                                <input type="checkbox" wire:model.live="hasPenaltyShootout" class="rounded text-red-600">
                                                <span class="text-sm text-gray-600">Elfmeterschiessen</span>
                                            </label>

                                            @if ($hasPenaltyShootout || ($goalsHome !== null && $goalsVisitor !== null && $goalsHome === $goalsVisitor))
                                                <div class="flex items-center gap-4">
                                                    <span class="text-sm text-gray-600">Sieger:</span>
                                                    @if ($game->homeTeam)
                                                        <label class="flex items-center gap-2 cursor-pointer">
                                                            <input type="radio" wire:model="penaltyWinnerTeamId" value="{{ $game->home_team_id }}" class="text-red-600">
                                                            <x-flag :code="$game->homeTeam->nation->code" size="text-sm" />
                                                            <span class="text-sm">{{ $game->homeTeam->nation->name }}</span>
                                                        </label>
                                                    @endif
                                                    @if ($game->visitorTeam)
                                                        <label class="flex items-center gap-2 cursor-pointer">
                                                            <input type="radio" wire:model="penaltyWinnerTeamId" value="{{ $game->visitor_team_id }}" class="text-red-600">
                                                            <x-flag :code="$game->visitorTeam->nation->code" size="text-sm" />
                                                            <span class="text-sm">{{ $game->visitorTeam->nation->name }}</span>
                                                        </label>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @error('goalsHome') <p class="text-red-500 text-sm text-center">{{ $message }}</p> @enderror
                                    @error('goalsVisitor') <p class="text-red-500 text-sm text-center">{{ $message }}</p> @enderror
                                    @error('penaltyWinnerTeamId') <p class="text-red-500 text-sm text-center">{{ $message }}</p> @enderror

                                    <!-- Footer -->
                                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" wire:model="isFinished" class="rounded text-green-600">
                                            <span class="text-sm text-gray-600">Spiel beendet (Fulltime)</span>
                                        </label>

                                        <div class="flex gap-2">
                                            <button type="button" wire:click="cancelEdit" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                                                Abbrechen
                                            </button>
                                            <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm rounded-md hover:bg-red-700">
                                                Speichern
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        @else
                            <!-- Game Display Row -->
                            <div class="p-4 flex items-center justify-between">
                                <div class="flex items-center gap-4 flex-1">
                                    <!-- Home Team -->
                                    <div class="flex items-center gap-2 flex-1 justify-end">
                                        <span class="font-medium">{{ $game->homeTeam?->nation->name ?? $game->home_team_placeholder }}</span>
                                        @if ($game->homeTeam)
                                            <x-flag :code="$game->homeTeam->nation->code" />
                                        @endif
                                    </div>

                                    <!-- Score -->
                                    <div class="px-4 text-center min-w-[80px]">
                                        @if ($game->is_finished)
                                            <span class="text-xl font-bold">{{ $game->goals_home }} : {{ $game->goals_visitor }}</span>
                                            @if ($game->has_penalty_shootout)
                                                <span class="text-xs text-gray-500 block">(n.E.)</span>
                                            @endif
                                        @else
                                            <span class="text-xl text-gray-300">- : -</span>
                                        @endif
                                    </div>

                                    <!-- Away Team -->
                                    <div class="flex items-center gap-2 flex-1">
                                        @if ($game->visitorTeam)
                                            <x-flag :code="$game->visitorTeam->nation->code" />
                                        @endif
                                        <span class="font-medium">{{ $game->visitorTeam?->nation->name ?? $game->visitor_team_placeholder }}</span>
                                    </div>
                                </div>

                                <!-- Status and Actions -->
                                <div class="flex items-center gap-4 ml-6">
                                    @if ($game->is_finished)
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Beendet</span>
                                        <button wire:click="editGame({{ $game->id }})" class="text-sm text-gray-500 hover:text-gray-700">
                                            Bearbeiten
                                        </button>
                                        <button wire:click="reopenGame({{ $game->id }})" wire:confirm="Spiel wieder offnen? Punkte mussen neu berechnet werden." class="text-sm text-red-500 hover:text-red-700">
                                            Offnen
                                        </button>
                                    @elseif ($game->kickoff_at->isPast())
                                        <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded animate-pulse">Live?</span>
                                        <button wire:click="editGame({{ $game->id }})" class="text-sm text-red-600 hover:text-red-800 font-medium">
                                            Ergebnis eintragen
                                        </button>
                                    @else
                                        <span class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded">Ausstehend</span>
                                        <button wire:click="editGame({{ $game->id }})" class="text-sm text-gray-500 hover:text-gray-700">
                                            Bearbeiten
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                        Keine Spiele fur diesen Filter gefunden.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
