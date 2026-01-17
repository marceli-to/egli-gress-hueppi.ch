<?php

use App\Models\SpecialTipp;
use App\Models\SpecialTippSpec;
use App\Models\Team;
use App\Helpers\TournamentHelper;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\RateLimiter;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $editingSpecId = null;
    public ?int $selectedTeamId = null;
    public ?int $predictedValue = null;
    public ?string $selectedRanking = null;

    #[Computed]
    public function tournament()
    {
        return TournamentHelper::active();
    }

    #[Computed]
    public function specialTippSpecs()
    {
        if (!$this->tournament) {
            return collect();
        }

        return SpecialTippSpec::where('tournament_id', $this->tournament->id)
            ->with(['tipps' => fn($q) => $q->where('user_id', auth()->id())->with('predictedTeam.nation'), 'team.nation'])
            ->get();
    }

    #[Computed]
    public function totalSpecialPoints()
    {
        return $this->specialTippSpecs->sum(fn($spec) => $spec->tipps->first()?->score ?? 0);
    }

    #[Computed]
    public function teams()
    {
        if (!$this->tournament) {
            return collect();
        }

        return Team::where('tournament_id', $this->tournament->id)
            ->with('nation')
            ->get()
            ->sortBy('nation.name');
    }

    #[Computed]
    public function teamsByGroup()
    {
        return $this->teams->groupBy('group_name');
    }

    #[Computed]
    public function swissRankingOptions()
    {
        return [
            'GROUP_STAGE' => 'Vorrunde',
            'ROUND_OF_16' => 'Achtelfinale',
            'QUARTER_FINAL' => 'Viertelfinale',
            'SEMI_FINAL' => 'Halbfinale',
            'RUNNER_UP' => 'Vizeweltmeister',
            'CHAMPION' => 'Weltmeister',
        ];
    }

    public function canEdit(): bool
    {
        if (!$this->tournament) {
            return false;
        }

        $firstGame = $this->tournament->games()->orderBy('kickoff_at')->first();
        return !$firstGame || $firstGame->kickoff_at->isFuture();
    }

    public function editTipp(int $specId): void
    {
        if (!$this->canEdit()) {
            return;
        }

        $spec = SpecialTippSpec::with(['tipps' => fn($q) => $q->where('user_id', auth()->id())])->find($specId);
        if (!$spec) {
            return;
        }

        $this->editingSpecId = $specId;
        $existingTipp = $spec->tipps->first();

        if ($existingTipp) {
            $this->selectedTeamId = $existingTipp->predicted_team_id;
            $this->predictedValue = $existingTipp->predicted_value;
            $this->selectedRanking = $existingTipp->predicted_ranking;
        } else {
            $this->selectedTeamId = null;
            $this->predictedValue = null;
            $this->selectedRanking = null;
        }
    }

    public function cancelEdit(): void
    {
        $this->editingSpecId = null;
        $this->selectedTeamId = null;
        $this->predictedValue = null;
        $this->selectedRanking = null;
    }

    public function saveTipp(): void
    {
        $key = 'special-tipp:' . auth()->id();

        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError('predictedValue', 'Zu viele Anfragen. Bitte warten.');
            return;
        }

        RateLimiter::hit($key, 60);

        if (!$this->canEdit()) {
            $this->cancelEdit();
            return;
        }

        $spec = SpecialTippSpec::find($this->editingSpecId);
        if (!$spec) {
            $this->cancelEdit();
            return;
        }

        if ($spec->type === 'TOTAL_GOALS') {
            $this->validate([
                'predictedValue' => 'required|integer|min:0|max:200',
            ]);
            $data = ['predicted_value' => $this->predictedValue];
        } elseif ($spec->type === 'FINAL_RANKING') {
            $this->validate([
                'selectedRanking' => 'required|in:GROUP_STAGE,ROUND_OF_16,QUARTER_FINAL,SEMI_FINAL,RUNNER_UP,CHAMPION',
            ]);
            $data = ['predicted_ranking' => $this->selectedRanking];
        } else {
            $this->validate([
                'selectedTeamId' => 'required|exists:teams,id',
            ]);
            $data = ['predicted_team_id' => $this->selectedTeamId];
        }

        SpecialTipp::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'special_tipp_spec_id' => $spec->id,
            ],
            $data
        );

        $this->cancelEdit();
    }

    public function selectTeamQuick(int $specId, int $teamId): void
    {
        $key = 'special-tipp:' . auth()->id();

        if (RateLimiter::tooManyAttempts($key, 60)) {
            return;
        }

        RateLimiter::hit($key, 60);

        if (!$this->canEdit()) {
            return;
        }

        $spec = SpecialTippSpec::find($specId);
        if (!$spec) {
            return;
        }

        SpecialTipp::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'special_tipp_spec_id' => $spec->id,
            ],
            ['predicted_team_id' => $teamId]
        );
    }

    public function selectRankingQuick(int $specId, string $ranking): void
    {
        $key = 'special-tipp:' . auth()->id();

        if (RateLimiter::tooManyAttempts($key, 60)) {
            return;
        }

        RateLimiter::hit($key, 60);

        if (!$this->canEdit()) {
            return;
        }

        $spec = SpecialTippSpec::find($specId);
        if (!$spec) {
            return;
        }

        SpecialTipp::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'special_tipp_spec_id' => $spec->id,
            ],
            ['predicted_ranking' => $ranking]
        );
    }

    public function getTeamsForSpec(SpecialTippSpec $spec)
    {
        if (str_starts_with($spec->name, 'WINNER_GROUP_')) {
            $group = substr($spec->name, -1);
            return $this->teamsByGroup[$group] ?? collect();
        }

        return $this->teams;
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bonustipps') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if ($this->tournament)
                @if ($this->tournament->is_complete)
                    <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-green-800">
                                Das Turnier ist beendet. Hier siehst du deine Ergebnisse.
                            </div>
                            <div class="text-green-900 font-bold text-lg">
                                {{ $this->totalSpecialPoints }} Bonuspunkte
                            </div>
                        </div>
                    </div>
                @elseif (!$this->canEdit())
                    <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-yellow-800">
                        Das Turnier hat begonnen. Bonustipps konnen nicht mehr geandert werden.
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Left Column: World Cup Winner + Group Winners -->
                    <div class="space-y-6">
                        <!-- Tournament Champion -->
                        @php $championSpec = $this->specialTippSpecs->firstWhere('name', 'WINNER_WORLDCUP'); @endphp
                        @if ($championSpec)
                            @php
                                $championTipp = $championSpec->tipps->first();
                                $actualWinner = $championSpec->team;
                                $isCorrect = $championTipp && $actualWinner && $championTipp->predicted_team_id === $actualWinner->id;
                            @endphp
                            <div class="bg-gradient-to-r from-yellow-400 to-yellow-500 overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-4 border-b border-yellow-500/30">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-semibold text-yellow-900">Weltmeister</h3>
                                        @if ($this->tournament->is_complete && $championTipp)
                                            <span class="px-2 py-1 {{ $isCorrect ? 'bg-green-600' : 'bg-gray-600' }} text-white text-xs font-bold rounded">
                                                {{ $championTipp->score ?? 0 }} / {{ $championSpec->value }}
                                            </span>
                                        @else
                                            <span class="px-2 py-1 bg-yellow-600 text-white text-xs font-bold rounded">
                                                {{ $championSpec->value }} Punkte
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="p-4">
                                    @if ($this->tournament->is_complete && $actualWinner)
                                        <!-- Show actual result -->
                                        <div class="mb-4">
                                            <p class="text-xs text-yellow-900/70 mb-1">Weltmeister</p>
                                            <div class="flex items-center gap-2">
                                                <x-flag :code="$actualWinner->nation->code" size="text-2xl" />
                                                <span class="text-xl font-bold text-yellow-900">{{ $actualWinner->nation->name }}</span>
                                            </div>
                                        </div>
                                        <div class="bg-white/80 rounded-lg p-3">
                                            <p class="text-xs text-yellow-900/70 mb-1">Dein Tipp</p>
                                            @if ($championTipp?->predictedTeam)
                                                <div class="flex items-center gap-2">
                                                    <x-flag :code="$championTipp->predictedTeam->nation->code" size="text-lg" />
                                                    <span class="font-medium {{ $isCorrect ? 'text-green-700' : 'text-gray-700' }}">
                                                        {{ $championTipp->predictedTeam->nation->name }}
                                                    </span>
                                                    @if ($isCorrect)
                                                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-gray-500">Kein Tipp</span>
                                            @endif
                                        </div>
                                    @else
                                        <!-- Current Selection -->
                                        <div class="flex items-center gap-3 mb-4">
                                            <svg class="w-8 h-8 text-yellow-900" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                                            </svg>
                                            @if ($championTipp?->predictedTeam)
                                                <x-flag :code="$championTipp->predictedTeam->nation->code" size="text-2xl" />
                                                <span class="text-xl font-bold text-yellow-900">{{ $championTipp->predictedTeam->nation->name }}</span>
                                            @else
                                                <span class="text-lg text-yellow-900/70">Noch nicht ausgewahlt</span>
                                            @endif
                                        </div>

                                        @if ($this->canEdit())
                                            <!-- Team Selection Grid -->
                                            <div class="grid grid-cols-4 gap-2">
                                                @foreach ($this->teams as $team)
                                                    <button
                                                        wire:click="selectTeamQuick({{ $championSpec->id }}, {{ $team->id }})"
                                                        class="p-2 bg-white/80 rounded-lg text-center hover:bg-white transition-colors {{ $championTipp?->predicted_team_id === $team->id ? 'ring-2 ring-yellow-600 bg-white' : '' }}"
                                                    >
                                                        <x-flag :code="$team->nation->code" size="text-lg" />
                                                        <span class="text-xs block mt-1 truncate">{{ $team->nation->name }}</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @elseif ($championTipp?->predictedTeam)
                                            <!-- Team Stats -->
                                            <div class="bg-white/80 rounded-lg p-3 mt-4">
                                                <table class="w-full text-sm">
                                                    <tr>
                                                        <td class="text-yellow-900/70 py-1">FIFA Ranking</td>
                                                        <td class="text-right font-medium text-yellow-900">{{ $championTipp->predictedTeam->nation->fifa_ranking ?? '-' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-yellow-900/70 py-1">WM-Titel</td>
                                                        <td class="text-right font-medium text-yellow-900">{{ $championTipp->predictedTeam->nation->champion_count ?? 0 }}</td>
                                                    </tr>
                                                </table>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Group Winners -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-4 border-b border-gray-100">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-semibold text-gray-800">Gruppensieger</h3>
                                    @if ($this->tournament->is_complete)
                                        @php
                                            $groupPoints = collect(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'])
                                                ->sum(fn($g) => $this->specialTippSpecs->firstWhere('name', 'WINNER_GROUP_' . $g)?->tipps->first()?->score ?? 0);
                                        @endphp
                                        <span class="text-sm font-medium {{ $groupPoints > 0 ? 'text-green-600' : 'text-gray-500' }}">
                                            {{ $groupPoints }} / 24
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-500">8 x 3 Punkte</span>
                                    @endif
                                </div>
                            </div>
                            <div class="p-4 grid grid-cols-2 gap-4">
                                @foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $group)
                                    @php
                                        $spec = $this->specialTippSpecs->firstWhere('name', 'WINNER_GROUP_' . $group);
                                        $tipp = $spec?->tipps->first();
                                        $actualWinner = $spec?->team;
                                        $groupTeams = $this->teamsByGroup[$group] ?? collect();
                                        $isCorrect = $tipp && $actualWinner && $tipp->predicted_team_id === $actualWinner->id;
                                    @endphp
                                    @if ($spec)
                                        <div class="border {{ $this->tournament->is_complete ? ($isCorrect ? 'border-green-300 bg-green-50' : 'border-gray-200') : 'border-gray-200' }} rounded-lg p-3">
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="w-6 h-6 bg-gray-800 text-white text-xs font-bold rounded flex items-center justify-center">{{ $group }}</span>
                                                @if ($this->tournament->is_complete && $actualWinner)
                                                    <x-flag :code="$actualWinner->nation->code" size="text-lg" />
                                                    <span class="text-sm font-medium">{{ $actualWinner->nation->name }}</span>
                                                @elseif ($tipp?->predictedTeam)
                                                    <x-flag :code="$tipp->predictedTeam->nation->code" size="text-lg" />
                                                    <span class="text-sm font-medium">{{ $tipp->predictedTeam->nation->name }}</span>
                                                @else
                                                    <span class="text-sm text-gray-400">Nicht gewahlt</span>
                                                @endif
                                            </div>

                                            @if ($this->tournament->is_complete && $tipp?->predictedTeam)
                                                <div class="text-xs {{ $isCorrect ? 'text-green-600' : 'text-gray-500' }}">
                                                    @if ($isCorrect)
                                                        <span class="font-medium">Punkte: {{ $tipp->score }}</span>
                                                    @else
                                                        <span class="font-medium">Dein Tipp: {{ $tipp->predictedTeam->nation->name }}</span>
                                                    @endif
                                                </div>
                                            @elseif (!$this->tournament->is_complete && $this->canEdit())
                                                <select
                                                    wire:change="selectTeamQuick({{ $spec->id }}, $event.target.value)"
                                                    class="w-full text-sm rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                >
                                                    <option value="">Auswahlen...</option>
                                                    @foreach ($groupTeams as $team)
                                                        <option value="{{ $team->id }}" {{ $tipp?->predicted_team_id === $team->id ? 'selected' : '' }}>
                                                            {{ $team->nation->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Switzerland Special -->
                    <div class="space-y-6">
                        <!-- Switzerland Goals -->
                        @php $swissGoalsSpec = $this->specialTippSpecs->firstWhere('name', 'TOTAL_GOALS_CH'); @endphp
                        @if ($swissGoalsSpec)
                            @php
                                $swissGoalsTipp = $swissGoalsSpec->tipps->first();
                                $actualGoals = $swissGoalsSpec->result_value;
                                $isCorrect = $swissGoalsTipp && $actualGoals !== null && $swissGoalsTipp->predicted_value == $actualGoals;
                            @endphp
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-4 border-b border-gray-100">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-semibold text-gray-800">Tore Schweiz</h3>
                                        @if ($this->tournament->is_complete && $swissGoalsTipp)
                                            <span class="px-2 py-1 {{ $isCorrect ? 'bg-green-600' : 'bg-gray-600' }} text-white text-xs font-bold rounded">
                                                {{ $swissGoalsTipp->score ?? 0 }} / {{ $swissGoalsSpec->value }}
                                            </span>
                                        @else
                                            <span class="px-2 py-1 bg-red-600 text-white text-xs font-bold rounded">
                                                {{ $swissGoalsSpec->value }} Punkte
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="p-4">
                                    @if ($this->tournament->is_complete && $actualGoals !== null)
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="text-center py-4 bg-gray-50 rounded-lg">
                                                <p class="text-xs text-gray-500 mb-1">Tatsachlich</p>
                                                <p class="text-3xl font-bold text-gray-900">{{ $actualGoals }}</p>
                                            </div>
                                            <div class="text-center py-4 {{ $isCorrect ? 'bg-green-50' : 'bg-gray-50' }} rounded-lg">
                                                <p class="text-xs text-gray-500 mb-1">Dein Tipp</p>
                                                <p class="text-3xl font-bold {{ $isCorrect ? 'text-green-600' : 'text-gray-900' }}">
                                                    {{ $swissGoalsTipp?->predicted_value ?? '-' }}
                                                    @if ($isCorrect)
                                                        <svg class="w-6 h-6 text-green-600 inline ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    @else
                                        <p class="text-sm text-gray-600 mb-4">
                                            Anzahl Tore der Schweiz (ohne Elfmeterschiessen)
                                        </p>

                                        @if ($editingSpecId === $swissGoalsSpec->id)
                                            <form wire:submit="saveTipp" class="space-y-3">
                                                <input
                                                    type="number"
                                                    wire:model="predictedValue"
                                                    min="0"
                                                    max="50"
                                                    class="w-full text-center text-2xl rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    placeholder="0"
                                                >
                                                @error('predictedValue') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                                                <div class="flex gap-2">
                                                    <button type="button" wire:click="cancelEdit" class="flex-1 px-3 py-2 text-sm text-gray-600 border rounded hover:bg-gray-50">
                                                        Abbrechen
                                                    </button>
                                                    <button type="submit" class="flex-1 px-3 py-2 text-sm bg-gray-900 text-white rounded hover:bg-gray-800">
                                                        Speichern
                                                    </button>
                                                </div>
                                            </form>
                                        @else
                                            <div class="text-center py-4">
                                                <p class="text-4xl font-bold {{ $swissGoalsTipp ? 'text-gray-900' : 'text-gray-300' }}">
                                                    {{ $swissGoalsTipp?->predicted_value ?? '-' }}
                                                </p>
                                                <p class="text-sm text-gray-500 mt-1">Tore</p>
                                            </div>
                                            @if ($this->canEdit())
                                                <button wire:click="editTipp({{ $swissGoalsSpec->id }})" class="w-full mt-2 px-3 py-2 text-sm text-indigo-600 border border-dashed border-gray-300 rounded hover:border-indigo-300">
                                                    {{ $swissGoalsTipp ? 'Andern' : 'Tippen' }}
                                                </button>
                                            @endif
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Switzerland Final Ranking -->
                        @php $swissRankingSpec = $this->specialTippSpecs->firstWhere('name', 'FINAL_RANKING_CH'); @endphp
                        @if ($swissRankingSpec)
                            @php
                                $swissRankingTipp = $swissRankingSpec->tipps->first();
                                $actualRanking = $swissRankingSpec->result_ranking;
                                $isCorrect = $swissRankingTipp && $actualRanking && $swissRankingTipp->predicted_ranking === $actualRanking;
                            @endphp
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-4 border-b border-gray-100">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-semibold text-gray-800">Abschneiden Schweiz</h3>
                                        @if ($this->tournament->is_complete && $swissRankingTipp)
                                            <span class="px-2 py-1 {{ $isCorrect ? 'bg-green-600' : 'bg-gray-600' }} text-white text-xs font-bold rounded">
                                                {{ $swissRankingTipp->score ?? 0 }} / {{ $swissRankingSpec->value }}
                                            </span>
                                        @else
                                            <span class="px-2 py-1 bg-red-600 text-white text-xs font-bold rounded">
                                                {{ $swissRankingSpec->value }} Punkte
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="p-4">
                                    @if ($this->tournament->is_complete && $actualRanking)
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="text-center py-4 bg-gray-50 rounded-lg">
                                                <p class="text-xs text-gray-500 mb-1">Tatsachlich</p>
                                                <p class="text-xl font-bold text-gray-900">{{ $this->swissRankingOptions[$actualRanking] ?? $actualRanking }}</p>
                                            </div>
                                            <div class="text-center py-4 {{ $isCorrect ? 'bg-green-50' : 'bg-gray-50' }} rounded-lg">
                                                <p class="text-xs text-gray-500 mb-1">Dein Tipp</p>
                                                <p class="text-xl font-bold {{ $isCorrect ? 'text-green-600' : 'text-gray-900' }}">
                                                    {{ $swissRankingTipp ? ($this->swissRankingOptions[$swissRankingTipp->predicted_ranking] ?? '-') : '-' }}
                                                    @if ($isCorrect)
                                                        <svg class="w-5 h-5 text-green-600 inline ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    @else
                                        <p class="text-sm text-gray-600 mb-4">
                                            Wie weit kommt die Schweiz?
                                        </p>

                                        @if ($this->canEdit())
                                            <div class="grid grid-cols-2 gap-2">
                                                @foreach ($this->swissRankingOptions as $key => $label)
                                                    <button
                                                        wire:click="selectRankingQuick({{ $swissRankingSpec->id }}, '{{ $key }}')"
                                                        class="px-3 py-2 text-sm rounded-lg transition-colors {{ $swissRankingTipp?->predicted_ranking === $key ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                                                    >
                                                        {{ $label }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="text-center py-4">
                                                <p class="text-xl font-bold {{ $swissRankingTipp ? 'text-gray-900' : 'text-gray-300' }}">
                                                    {{ $swissRankingTipp ? ($this->swissRankingOptions[$swissRankingTipp->predicted_ranking] ?? '-') : '-' }}
                                                </p>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Points Overview -->
                        <div class="bg-gray-50 overflow-hidden shadow-sm sm:rounded-lg p-4">
                            <h4 class="font-semibold text-gray-800 mb-3">
                                @if ($this->tournament->is_complete)
                                    Deine Bonuspunkte
                                @else
                                    Punkteubersicht Bonustipps
                                @endif
                            </h4>
                            <table class="w-full text-sm">
                                @if ($this->tournament->is_complete)
                                    @php
                                        $wcSpec = $this->specialTippSpecs->firstWhere('name', 'WINNER_WORLDCUP');
                                        $wcPoints = $wcSpec?->tipps->first()?->score ?? 0;
                                        $groupPoints = collect(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'])
                                            ->sum(fn($g) => $this->specialTippSpecs->firstWhere('name', 'WINNER_GROUP_' . $g)?->tipps->first()?->score ?? 0);
                                        $goalsSpec = $this->specialTippSpecs->firstWhere('name', 'TOTAL_GOALS_CH');
                                        $goalsPoints = $goalsSpec?->tipps->first()?->score ?? 0;
                                        $rankingSpec = $this->specialTippSpecs->firstWhere('name', 'FINAL_RANKING_CH');
                                        $rankingPoints = $rankingSpec?->tipps->first()?->score ?? 0;
                                    @endphp
                                    <tr>
                                        <td class="py-1 text-gray-600">Weltmeister</td>
                                        <td class="py-1 text-right font-medium {{ $wcPoints > 0 ? 'text-green-600' : '' }}">{{ $wcPoints }} / {{ $wcSpec?->value ?? 0 }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-600">8 Gruppensieger</td>
                                        <td class="py-1 text-right font-medium {{ $groupPoints > 0 ? 'text-green-600' : '' }}">{{ $groupPoints }} / 24</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-600">Tore Schweiz</td>
                                        <td class="py-1 text-right font-medium {{ $goalsPoints > 0 ? 'text-green-600' : '' }}">{{ $goalsPoints }} / {{ $goalsSpec?->value ?? 0 }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-600">Abschneiden Schweiz</td>
                                        <td class="py-1 text-right font-medium {{ $rankingPoints > 0 ? 'text-green-600' : '' }}">{{ $rankingPoints }} / {{ $rankingSpec?->value ?? 0 }}</td>
                                    </tr>
                                    <tr class="border-t border-gray-300">
                                        <td class="py-2 font-semibold text-gray-800">Total</td>
                                        <td class="py-2 text-right font-bold text-gray-900">{{ $this->totalSpecialPoints }} Punkte</td>
                                    </tr>
                                @else
                                    <tr>
                                        <td class="py-1 text-gray-600">Weltmeister</td>
                                        <td class="py-1 text-right font-medium">{{ $this->specialTippSpecs->firstWhere('name', 'WINNER_WORLDCUP')?->value ?? 0 }} Punkte</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-600">8 Gruppensieger</td>
                                        <td class="py-1 text-right font-medium">je 3 Punkte</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-600">Tore Schweiz</td>
                                        <td class="py-1 text-right font-medium">{{ $this->specialTippSpecs->firstWhere('name', 'TOTAL_GOALS_CH')?->value ?? 0 }} Punkte</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-600">Abschneiden Schweiz</td>
                                        <td class="py-1 text-right font-medium">{{ $this->specialTippSpecs->firstWhere('name', 'FINAL_RANKING_CH')?->value ?? 0 }} Punkte</td>
                                    </tr>
                                @endif
                            </table>
                        </div>
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
