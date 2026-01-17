<?php

use App\Models\Game;
use App\Models\GameTipp;
use App\Models\TippGroup;
use App\Models\User;
use App\Models\UserScore;
use App\Helpers\TournamentHelper;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $selectedTippGroupId = null;
    public ?int $compareUserId = null;

    #[Computed]
    public function tournament()
    {
        return TournamentHelper::active();
    }

    #[Computed]
    public function tippGroups()
    {
        return auth()->user()->tippGroups;
    }

    #[Computed]
    public function rankings()
    {
        if (!$this->tournament) {
            return collect();
        }

        $query = UserScore::where('tournament_id', $this->tournament->id)
            ->with(['user', 'championTeam.nation']);

        if ($this->selectedTippGroupId) {
            $groupMemberIds = TippGroup::find($this->selectedTippGroupId)
                ?->members()
                ->pluck('users.id')
                ->toArray() ?? [];

            $query->whereIn('user_id', $groupMemberIds);
        }

        return $query->orderBy('rank')
            ->orderByDesc('total_points')
            ->get()
            ->map(function ($score, $index) {
                return (object) [
                    'rank' => $score->rank ?? ($index + 1),
                    'user' => $score->user,
                    'championTeam' => $score->championTeam,
                    'total_points' => $score->total_points,
                    'game_points' => $score->game_points,
                    'special_points' => $score->special_points,
                ];
            });
    }

    #[Computed]
    public function currentUserRank()
    {
        foreach ($this->rankings as $index => $ranking) {
            if ($ranking->user->id === auth()->id()) {
                return $ranking->rank;
            }
        }
        return null;
    }

    #[Computed]
    public function compareUser()
    {
        if (!$this->compareUserId) {
            return null;
        }
        return User::find($this->compareUserId);
    }

    #[Computed]
    public function comparisonGames()
    {
        if (!$this->tournament || !$this->compareUserId) {
            return collect();
        }

        return Game::where('tournament_id', $this->tournament->id)
            ->where('is_finished', true)
            ->with([
                'homeTeam.nation',
                'visitorTeam.nation',
                'penaltyWinnerTeam.nation',
                'tipps' => fn($q) => $q->whereIn('user_id', [auth()->id(), $this->compareUserId])->with('penaltyWinnerTeam.nation')
            ])
            ->orderByDesc('kickoff_at')
            ->get()
            ->map(function ($game) {
                $myTipp = $game->tipps->firstWhere('user_id', auth()->id());
                $theirTipp = $game->tipps->firstWhere('user_id', $this->compareUserId);

                return (object) [
                    'game' => $game,
                    'myTipp' => $myTipp,
                    'theirTipp' => $theirTipp,
                ];
            });
    }

    public function selectTippGroup(?int $groupId): void
    {
        $this->selectedTippGroupId = $groupId;
    }

    public function selectCompareUser(int $userId): void
    {
        if ($userId === auth()->id()) {
            return;
        }
        $this->compareUserId = $this->compareUserId === $userId ? null : $userId;
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Rangliste') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if ($this->tournament)
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Left Column: Rankings -->
                    <div class="space-y-6">
                        <!-- Current User Stats -->
                        @if ($this->currentUserRank)
                            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-6 text-white">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm opacity-80">Dein Rang</p>
                                            <p class="text-4xl font-bold">#{{ $this->currentUserRank }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm opacity-80">Punkte</p>
                                            @php
                                                $myScore = $this->rankings->first(fn($r) => $r->user->id === auth()->id());
                                            @endphp
                                            <p class="text-4xl font-bold">{{ $myScore?->total_points ?? 0 }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Ranking Table -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-4 border-b border-gray-100">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-semibold text-gray-800">
                                        @if ($selectedTippGroupId)
                                            {{ $this->tippGroups->firstWhere('id', $selectedTippGroupId)?->name }}
                                        @else
                                            Gesamtrangliste
                                        @endif
                                    </h3>
                                    @if ($this->tippGroups->isNotEmpty())
                                        <select
                                            wire:change="selectTippGroup($event.target.value === '' ? null : $event.target.value)"
                                            class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Alle</option>
                                            @foreach ($this->tippGroups as $group)
                                                <option value="{{ $group->id }}" {{ $selectedTippGroupId === $group->id ? 'selected' : '' }}>
                                                    {{ $group->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                            </div>
                            <table class="min-w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase w-12">R</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Spieler</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-12">C</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase w-16">P</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse ($this->rankings as $ranking)
                                        @php
                                            $isCurrentUser = $ranking->user->id === auth()->id();
                                            $isSelected = $ranking->user->id === $compareUserId;
                                        @endphp
                                        <tr
                                            wire:click="selectCompareUser({{ $ranking->user->id }})"
                                            class="cursor-pointer transition-colors {{ $isCurrentUser ? 'bg-indigo-50' : ($isSelected ? 'bg-yellow-50' : 'hover:bg-gray-50') }}"
                                        >
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                                @if ($ranking->rank === 1)
                                                    <span class="text-lg">1</span>
                                                @elseif ($ranking->rank === 2)
                                                    <span class="text-lg">2</span>
                                                @elseif ($ranking->rank === 3)
                                                    <span class="text-lg">3</span>
                                                @else
                                                    {{ $ranking->rank }}
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-sm">
                                                <div class="flex items-center gap-2">
                                                    <span class="{{ $isCurrentUser ? 'text-indigo-600 font-medium' : 'text-gray-900' }}">
                                                        {{ $ranking->user->display_name ?? $ranking->user->name }}
                                                    </span>
                                                    @if ($isCurrentUser)
                                                        <span class="text-xs text-gray-500">(Du)</span>
                                                    @endif
                                                    @if ($isSelected)
                                                        <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                @if ($ranking->championTeam?->nation)
                                                    <x-flag :code="$ranking->championTeam->nation->code" size="text-sm" />
                                                @else
                                                    <span class="text-gray-300">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-right text-sm font-semibold text-gray-900">
                                                {{ $ranking->total_points }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                                Noch keine Rangliste vorhanden.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right Column: Tip Comparison -->
                    <div class="space-y-6">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-4 border-b border-gray-100">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-semibold text-gray-800">
                                        @if ($this->compareUser)
                                            Tippvergleich mit {{ $this->compareUser->display_name ?? $this->compareUser->name }}
                                        @else
                                            Tippvergleich
                                        @endif
                                    </h3>
                                    <div class="relative group">
                                        <button type="button" class="text-gray-400 hover:text-gray-600">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </button>
                                        <div class="absolute right-0 mt-2 w-72 bg-gray-900 text-white text-xs rounded-lg p-4 invisible group-hover:visible z-50 shadow-lg">
                                            <p class="font-semibold mb-2">Punktevergabe K.O.-Spiele</p>
                                            <ul class="space-y-1 text-gray-300">
                                                <li>• 10 Pkt: Korrekter Sieger (Team das weiterkommt)</li>
                                                <li>• 3 Pkt: Korrekte Tendenz (n. 90/120 Min.)</li>
                                                <li>• 3 Pkt: Korrekte Tordifferenz</li>
                                                <li>• 2 Pkt: Korrekte Heimtore</li>
                                                <li>• 2 Pkt: Korrekte Gästetore</li>
                                            </ul>
                                            <p class="mt-2 text-gray-400">(n.V.) = nach Verlängerung/Elfmeter</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if ($this->compareUser)
                                <div class="divide-y divide-gray-100 max-h-[600px] overflow-y-auto">
                                    @forelse ($this->comparisonGames as $comparison)
                                        <div class="p-4">
                                            <!-- Game Info -->
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="flex items-center gap-2 text-sm">
                                                    @if ($comparison->game->homeTeam)
                                                        <x-flag :code="$comparison->game->homeTeam->nation->code" size="text-sm" />
                                                    @endif
                                                    <span class="font-medium">
                                                        {{ $comparison->game->goals_home }} : {{ $comparison->game->goals_visitor }}
                                                    </span>
                                                    @if ($comparison->game->has_penalty_shootout && $comparison->game->penaltyWinnerTeam)
                                                        <span class="text-xs text-gray-500">n.V., i.E. {{ $comparison->game->penaltyWinnerTeam->nation->name }}</span>
                                                    @endif
                                                    @if ($comparison->game->visitorTeam)
                                                        <x-flag :code="$comparison->game->visitorTeam->nation->code" size="text-sm" />
                                                    @endif
                                                </div>
                                                <span class="text-xs text-gray-500">
                                                    {{ $comparison->game->kickoff_at->format('d.m.Y') }}
                                                </span>
                                            </div>

                                            <!-- Tip Comparison -->
                                            <div class="grid grid-cols-2 gap-4 text-sm">
                                                <!-- My Tip -->
                                                <div class="p-2 bg-indigo-50 rounded">
                                                    <p class="text-xs text-indigo-600 mb-1">Dein Tipp</p>
                                                    @if ($comparison->myTipp)
                                                        <div class="flex items-center justify-between">
                                                            <div>
                                                                <p class="font-semibold">
                                                                    {{ $comparison->myTipp->goals_home }} : {{ $comparison->myTipp->goals_visitor }}
                                                                </p>
                                                                @if ($comparison->myTipp->penalty_winner_team_id)
                                                                    <span class="text-xs text-gray-500">(P: {{ $comparison->myTipp->penaltyWinnerTeam?->nation->name }})</span>
                                                                @endif
                                                            </div>
                                                            <x-score-badge :tipp="$comparison->myTipp" :game="$comparison->game" />
                                                        </div>
                                                    @else
                                                        <p class="text-gray-400">Kein Tipp</p>
                                                    @endif
                                                </div>

                                                <!-- Their Tip -->
                                                <div class="p-2 bg-yellow-50 rounded">
                                                    <p class="text-xs text-yellow-600 mb-1">{{ $this->compareUser->name }}</p>
                                                    @if ($comparison->theirTipp)
                                                        <div class="flex items-center justify-between">
                                                            <div>
                                                                <p class="font-semibold">
                                                                    {{ $comparison->theirTipp->goals_home }} : {{ $comparison->theirTipp->goals_visitor }}
                                                                </p>
                                                                @if ($comparison->theirTipp->penalty_winner_team_id)
                                                                    <span class="text-xs text-gray-500">(P: {{ $comparison->theirTipp->penaltyWinnerTeam?->nation->name }})</span>
                                                                @endif
                                                            </div>
                                                            <x-score-badge :tipp="$comparison->theirTipp" :game="$comparison->game" />
                                                        </div>
                                                    @else
                                                        <p class="text-gray-400">Kein Tipp</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="p-6 text-center text-gray-500">
                                            Noch keine abgeschlossenen Spiele.
                                        </div>
                                    @endforelse
                                </div>
                            @else
                                <div class="p-6 text-center text-gray-500">
                                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <p>Wahle einen Spieler aus der Rangliste, um seine Tipps mit deinen zu vergleichen.</p>
                                </div>
                            @endif
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
