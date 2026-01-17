<?php

use App\Models\Game;
use App\Models\GameTipp;
use App\Models\Team;
use App\Models\Tournament;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\RateLimiter;

new #[Layout('layouts.app')] class extends Component
{
    public string $selectedGroup = 'A';
    public bool $isKnockout = false;
    public string $knockoutStage = 'ROUND_OF_16';

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
    public function groups()
    {
        return ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    }

    #[Computed]
    public function knockoutStages()
    {
        return [
            'ROUND_OF_16' => 'Achtelfinale',
            'QUARTER_FINAL' => 'Viertelfinale',
            'SEMI_FINAL' => 'Halbfinale',
            'THIRD_PLACE' => 'Spiel um Platz 3',
            'FINAL' => 'Finale',
        ];
    }

    #[Computed]
    public function games()
    {
        if (!$this->tournament) {
            return collect();
        }

        $query = Game::with(['homeTeam.nation', 'visitorTeam.nation', 'penaltyWinnerTeam.nation', 'location', 'tipps' => function ($q) {
            $q->where('user_id', auth()->id())->with('penaltyWinnerTeam.nation');
        }])
            ->where('tournament_id', $this->tournament->id)
            ->orderBy('kickoff_at');

        if ($this->isKnockout) {
            return $query->where('game_type', $this->knockoutStage)->get();
        }

        return $query->where('group_name', $this->selectedGroup)->get();
    }

    #[Computed]
    public function groupRanking()
    {
        if (!$this->tournament || $this->isKnockout) {
            return collect();
        }

        // Get teams for the selected group
        $teams = Team::where('tournament_id', $this->tournament->id)
            ->where('group_name', $this->selectedGroup)
            ->with('nation')
            ->get();

        // Calculate standings from finished games
        $standings = [];
        foreach ($teams as $team) {
            $standings[$team->id] = [
                'team' => $team,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'goal_diff' => 0,
                'points' => 0,
            ];
        }

        // Get finished games for this group
        $finishedGames = Game::where('tournament_id', $this->tournament->id)
            ->where('group_name', $this->selectedGroup)
            ->where('is_finished', true)
            ->get();

        foreach ($finishedGames as $game) {
            if (!isset($standings[$game->home_team_id]) || !isset($standings[$game->visitor_team_id])) {
                continue;
            }

            // Home team stats
            $standings[$game->home_team_id]['played']++;
            $standings[$game->home_team_id]['goals_for'] += $game->goals_home;
            $standings[$game->home_team_id]['goals_against'] += $game->goals_visitor;

            // Visitor team stats
            $standings[$game->visitor_team_id]['played']++;
            $standings[$game->visitor_team_id]['goals_for'] += $game->goals_visitor;
            $standings[$game->visitor_team_id]['goals_against'] += $game->goals_home;

            // Points
            if ($game->goals_home > $game->goals_visitor) {
                $standings[$game->home_team_id]['won']++;
                $standings[$game->home_team_id]['points'] += 3;
                $standings[$game->visitor_team_id]['lost']++;
            } elseif ($game->goals_home < $game->goals_visitor) {
                $standings[$game->visitor_team_id]['won']++;
                $standings[$game->visitor_team_id]['points'] += 3;
                $standings[$game->home_team_id]['lost']++;
            } else {
                $standings[$game->home_team_id]['drawn']++;
                $standings[$game->home_team_id]['points'] += 1;
                $standings[$game->visitor_team_id]['drawn']++;
                $standings[$game->visitor_team_id]['points'] += 1;
            }
        }

        // Calculate goal difference
        foreach ($standings as &$standing) {
            $standing['goal_diff'] = $standing['goals_for'] - $standing['goals_against'];
        }

        // Sort by points, then goal diff, then goals for
        uasort($standings, function ($a, $b) {
            if ($a['points'] !== $b['points']) {
                return $b['points'] - $a['points'];
            }
            if ($a['goal_diff'] !== $b['goal_diff']) {
                return $b['goal_diff'] - $a['goal_diff'];
            }
            return $b['goals_for'] - $a['goals_for'];
        });

        return collect(array_values($standings));
    }

    public function selectGroup(string $group): void
    {
        $this->isKnockout = false;
        $this->selectedGroup = $group;
        $this->cancelEdit();
    }

    public function selectKnockout(): void
    {
        $this->isKnockout = true;
        $this->knockoutStage = 'ROUND_OF_16';
        $this->cancelEdit();
    }

    public function selectKnockoutStage(string $stage): void
    {
        $this->knockoutStage = $stage;
        $this->cancelEdit();
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
        $key = 'save-tipp:' . auth()->id();

        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError('goalsHome', 'Zu viele Anfragen. Bitte warten.');
            return;
        }

        RateLimiter::hit($key, 60);

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
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Spiele') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if ($this->tournament)
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Left Column: Group/Stage Browser + Ranking/Bracket -->
                    <div class="space-y-6">
                        <!-- Group Browser -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->groups as $group)
                                    <button
                                        wire:click="selectGroup('{{ $group }}')"
                                        class="w-8 h-8 rounded-lg text-sm font-semibold transition-colors {{ !$isKnockout && $selectedGroup === $group ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                                    >
                                        {{ $group }}
                                    </button>
                                @endforeach
                                <div>
                                  <button
                                      wire:click="selectKnockout"
                                      class="px-4 h-8 rounded-lg text-sm font-semibold transition-colors {{ $isKnockout ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                                  >
                                      K.O.
                                  </button>
                                </div>
                            </div>
                        </div>

                        @if (!$isKnockout)
                            <!-- Group Ranking Table -->
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-4 border-b border-gray-100">
                                    <h3 class="font-semibold text-gray-800">Gruppe {{ $selectedGroup }}</h3>
                                </div>
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase w-8">#</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Team</th>
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-10">Sp</th>
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-10">S</th>
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-10">U</th>
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-10">N</th>
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-12">Diff</th>
                                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-10">Pkt</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($this->groupRanking as $index => $standing)
                                            <tr class="{{ $index < 2 ? 'bg-green-50' : '' }}">
                                                <td class="px-3 py-2 font-medium">{{ $index + 1 }}</td>
                                                <td class="px-3 py-2">
                                                    <div class="flex items-center gap-2">
                                                        <x-flag :code="$standing['team']->nation->code" size="text-sm" />
                                                        <span>{{ $standing['team']->nation->name }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2 text-center">{{ $standing['played'] }}</td>
                                                <td class="px-3 py-2 text-center">{{ $standing['won'] }}</td>
                                                <td class="px-3 py-2 text-center">{{ $standing['drawn'] }}</td>
                                                <td class="px-3 py-2 text-center">{{ $standing['lost'] }}</td>
                                                <td class="px-3 py-2 text-center {{ $standing['goal_diff'] > 0 ? 'text-green-600' : ($standing['goal_diff'] < 0 ? 'text-red-600' : '') }}">
                                                    {{ $standing['goal_diff'] > 0 ? '+' : '' }}{{ $standing['goal_diff'] }}
                                                </td>
                                                <td class="px-3 py-2 text-center font-bold">{{ $standing['points'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <!-- Knockout Stage Browser -->
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="font-semibold text-gray-800">K.O.-Runde</h3>
                                    <div class="relative group">
                                        <button type="button" class="text-gray-400 hover:text-gray-600">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </button>
                                        <div class="absolute left-0 mt-2 w-72 bg-gray-900 text-white text-xs rounded-lg p-4 invisible group-hover:visible z-50 shadow-lg">
                                            <p class="font-semibold mb-2">Punktevergabe (max 20 Pkt)</p>
                                            <ul class="space-y-1 text-gray-300">
                                                <li>• 10 Pkt: Korrekter Sieger (Team das weiterkommt)</li>
                                                <li>• 3 Pkt: Korrekte Tendenz (n. 90/120 Min.)</li>
                                                <li>• 3 Pkt: Korrekte Tordifferenz</li>
                                                <li>• 2 Pkt: Korrekte Heimtore</li>
                                                <li>• 2 Pkt: Korrekte Gästetore</li>
                                            </ul>
                                            <p class="mt-2 text-gray-400">Bei Unentschieden nach 90/120 Min. musst du den Elfmeter-Sieger tippen.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($this->knockoutStages as $stage => $label)
                                        <button
                                            wire:click="selectKnockoutStage('{{ $stage }}')"
                                            class="px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ $knockoutStage === $stage ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                                        >
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Right Column: Games List -->
                    <div class="space-y-4">
                        @forelse ($this->games as $game)
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-4">
                                    <!-- Game Header -->
                                    <div class="flex items-center justify-between mb-3 text-xs text-gray-500">
                                        <span>{{ $game->kickoff_at->format('D, d.m.Y - H:i') }}</span>
                                        <span>{{ $game->location->city }}</span>
                                    </div>

                                    <!-- Teams and Score -->
                                    <div class="flex items-center justify-between gap-4">
                                        <!-- Home Team -->
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                @if ($game->homeTeam)
                                                    <x-flag :code="$game->homeTeam->nation->code" />
                                                @endif
                                                <span class="font-medium text-sm">
                                                    {{ $game->homeTeam?->nation->name ?? $game->home_team_placeholder }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Score -->
                                        <div class="flex flex-col items-center gap-1 px-4">
                                            @if ($game->is_finished)
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xl font-bold">{{ $game->goals_home }}</span>
                                                    <span class="text-gray-400">:</span>
                                                    <span class="text-xl font-bold">{{ $game->goals_visitor }}</span>
                                                </div>
                                                @if ($game->has_penalty_shootout && $game->penaltyWinnerTeam)
                                                    <span class="text-xs text-gray-500">n.V., i.E. {{ $game->penaltyWinnerTeam->nation->name }}</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400">-:-</span>
                                            @endif
                                        </div>

                                        <!-- Visitor Team -->
                                        <div class="flex-1 text-right">
                                            <div class="flex items-center gap-2 justify-end">
                                                <span class="font-medium text-sm">
                                                    {{ $game->visitorTeam?->nation->name ?? $game->visitor_team_placeholder }}
                                                </span>
                                                @if ($game->visitorTeam)
                                                    <x-flag :code="$game->visitorTeam->nation->code" />
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- User's Tipp -->
                                    @php $userTipp = $game->tipps->first(); @endphp

                                    @if ($editingGameId === $game->id)
                                        <!-- Edit Form -->
                                        <div class="mt-4 pt-4 border-t border-gray-100">
                                            <form wire:submit="saveTipp" class="space-y-3">
                                                <div class="flex items-center justify-center gap-3">
                                                    <input
                                                        type="number"
                                                        wire:model="goalsHome"
                                                        min="0"
                                                        max="20"
                                                        class="w-14 text-center rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    >
                                                    <span class="text-gray-400">:</span>
                                                    <input
                                                        type="number"
                                                        wire:model="goalsVisitor"
                                                        min="0"
                                                        max="20"
                                                        class="w-14 text-center rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    >
                                                </div>

                                                @if ($game->isKnockoutGame() && $goalsHome !== null && $goalsVisitor !== null && $goalsHome === $goalsVisitor)
                                                    <div class="text-center">
                                                        <p class="text-xs text-gray-600 mb-2">Sieger im Elfmeterschiessen:</p>
                                                        <div class="flex justify-center gap-4">
                                                            @if ($game->homeTeam)
                                                                <label class="flex items-center gap-2 cursor-pointer">
                                                                    <input type="radio" wire:model="penaltyWinner" value="{{ $game->home_team_id }}" class="text-indigo-600">
                                                                    <x-flag :code="$game->homeTeam->nation->code" size="text-sm" />
                                                                    <span class="text-sm">{{ $game->homeTeam->nation->name }}</span>
                                                                </label>
                                                            @endif
                                                            @if ($game->visitorTeam)
                                                                <label class="flex items-center gap-2 cursor-pointer">
                                                                    <input type="radio" wire:model="penaltyWinner" value="{{ $game->visitor_team_id }}" class="text-indigo-600">
                                                                    <x-flag :code="$game->visitorTeam->nation->code" size="text-sm" />
                                                                    <span class="text-sm">{{ $game->visitorTeam->nation->name }}</span>
                                                                </label>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endif

                                                @error('goalsHome') <p class="text-red-500 text-xs text-center">{{ $message }}</p> @enderror
                                                @error('goalsVisitor') <p class="text-red-500 text-xs text-center">{{ $message }}</p> @enderror
                                                @error('penaltyWinner') <p class="text-red-500 text-xs text-center">{{ $message }}</p> @enderror

                                                <div class="flex justify-center gap-2">
                                                    <button type="button" wire:click="cancelEdit" class="px-3 py-1.5 text-xs text-gray-600 hover:text-gray-800">
                                                        Abbrechen
                                                    </button>
                                                    <button type="submit" class="px-3 py-1.5 bg-gray-900 text-white text-xs rounded-md hover:bg-gray-800">
                                                        Speichern
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    @else
                                        <div class="mt-3 pt-3 border-t border-gray-100">
                                            @if ($userTipp)
                                                <div class="flex items-center justify-between">
                                                    <div class="text-sm">
                                                        <span class="text-gray-500">Dein Tipp:</span>
                                                        <span class="ml-2 font-semibold">{{ $userTipp->goals_home }} : {{ $userTipp->goals_visitor }}</span>
                                                        @if ($userTipp->penalty_winner_team_id)
                                                            <span class="text-gray-500 text-xs ml-1">(P: {{ $userTipp->penaltyWinnerTeam?->nation->name }})</span>
                                                        @endif
                                                    </div>
                                                    @if ($game->is_finished)
                                                        <x-score-badge :tipp="$userTipp" :game="$game" />
                                                    @elseif ($game->canTipp())
                                                        <button wire:click="editTipp({{ $game->id }})" class="text-xs text-indigo-600 hover:text-indigo-800">
                                                            Andern
                                                        </button>
                                                    @endif
                                                </div>
                                            @elseif ($game->canTipp())
                                                <button wire:click="editTipp({{ $game->id }})" class="w-full py-2 text-xs text-indigo-600 border border-dashed border-gray-300 rounded hover:border-indigo-300">
                                                    + Tipp abgeben
                                                </button>
                                            @else
                                                <p class="text-xs text-gray-400 text-center">Tippfrist abgelaufen</p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                                Keine Spiele fur diese Auswahl.
                            </div>
                        @endforelse
                    </div>
                </div>
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                    Kein aktives Turnier gefunden.
                </div>
            @endif
        </div>
    </div>
</div>
