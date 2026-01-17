<?php

use App\Models\Game;
use App\Models\GameTipp;
use App\Models\Tournament;
use App\Models\UserScore;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts.app')] class extends Component
{
    #[Computed]
    public function tournament()
    {
        return Tournament::where('is_active', true)->first();
    }

    #[Computed]
    public function upcomingGames()
    {
        if (!$this->tournament) {
            return collect();
        }

        return Game::with(['homeTeam.nation', 'visitorTeam.nation', 'location', 'tipps' => fn($q) => $q->where('user_id', auth()->id())])
            ->where('tournament_id', $this->tournament->id)
            ->where('is_finished', false)
            ->orderBy('kickoff_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function recentResults()
    {
        if (!$this->tournament) {
            return collect();
        }

        return Game::with(['homeTeam.nation', 'visitorTeam.nation', 'tipps' => fn($q) => $q->where('user_id', auth()->id())])
            ->where('tournament_id', $this->tournament->id)
            ->where('is_finished', true)
            ->orderByDesc('kickoff_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function userStats()
    {
        if (!$this->tournament) {
            return null;
        }

        $totalTipps = GameTipp::whereHas('game', fn($q) => $q->where('tournament_id', $this->tournament->id))
            ->where('user_id', auth()->id())
            ->count();

        $totalPoints = GameTipp::whereHas('game', fn($q) => $q->where('tournament_id', $this->tournament->id)->where('is_finished', true))
            ->where('user_id', auth()->id())
            ->sum('score');

        $perfectTipps = GameTipp::whereHas('game', fn($q) => $q->where('tournament_id', $this->tournament->id)->where('is_finished', true))
            ->where('user_id', auth()->id())
            ->where('score', 4)
            ->count();

        return (object) [
            'total_tipps' => $totalTipps,
            'total_points' => $totalPoints,
            'perfect_tipps' => $perfectTipps,
        ];
    }

    #[Computed]
    public function missingTippsCount()
    {
        if (!$this->tournament) {
            return 0;
        }

        $upcomingGamesCount = Game::where('tournament_id', $this->tournament->id)
            ->where('is_finished', false)
            ->whereNotNull('home_team_id')
            ->whereNotNull('visitor_team_id')
            ->count();

        $userTippsCount = GameTipp::whereHas('game', fn($q) => $q->where('tournament_id', $this->tournament->id)->where('is_finished', false))
            ->where('user_id', auth()->id())
            ->count();

        return max(0, $upcomingGamesCount - $userTippsCount);
    }
}; ?>

<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Dashboard') }}
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        @if ($this->tournament)
            <!-- Welcome & Stats -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">
                    Welcome back, {{ auth()->user()->display_name ?? auth()->user()->name }}!
                </h1>
                <p class="text-gray-600">{{ $this->tournament->name }} - Make your predictions and climb the ranking!</p>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Total Points</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $this->userStats?->total_points ?? 0 }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Predictions Made</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $this->userStats?->total_tipps ?? 0 }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Perfect Tipps</p>
                    <p class="text-3xl font-bold text-green-600">{{ $this->userStats?->perfect_tipps ?? 0 }}</p>
                </div>
                @if ($this->missingTippsCount > 0)
                    <a href="{{ route('games') }}" wire:navigate class="bg-red-50 overflow-hidden shadow-sm sm:rounded-lg p-6 hover:bg-red-100 transition-colors">
                        <p class="text-sm text-red-600">Missing Tipps</p>
                        <p class="text-3xl font-bold text-red-600">{{ $this->missingTippsCount }}</p>
                    </a>
                @else
                    <div class="bg-green-50 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <p class="text-sm text-green-600">Missing Tipps</p>
                        <p class="text-3xl font-bold text-green-600">0</p>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Upcoming Games -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">Upcoming Games</h2>
                        <a href="{{ route('games') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-800">View all →</a>
                    </div>
                    <div class="space-y-3">
                        @forelse ($this->upcomingGames as $game)
                            @php $userTipp = $game->tipps->first(); @endphp
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs text-gray-500">{{ $game->kickoff_at->format('D, d M - H:i') }}</span>
                                    @if ($userTipp)
                                        <span class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded">Tipp: {{ $userTipp->goals_home }}:{{ $userTipp->goals_visitor }}</span>
                                    @elseif ($game->canTipp())
                                        <span class="text-xs px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded">No tipp yet</span>
                                    @endif
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        @if ($game->homeTeam)
                                            <x-flag :code="$game->homeTeam->nation->code" size="text-lg" />
                                        @endif
                                        <span class="font-medium text-sm">{{ $game->homeTeam?->nation->name ?? $game->home_team_placeholder }}</span>
                                    </div>
                                    <span class="text-gray-400 text-sm">vs</span>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-sm">{{ $game->visitorTeam?->nation->name ?? $game->visitor_team_placeholder }}</span>
                                        @if ($game->visitorTeam)
                                            <x-flag :code="$game->visitorTeam->nation->code" size="text-lg" />
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                                No upcoming games.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Recent Results -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">Recent Results</h2>
                        <a href="{{ route('games', ['filter' => 'finished']) }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-800">View all →</a>
                    </div>
                    <div class="space-y-3">
                        @forelse ($this->recentResults as $game)
                            @php $userTipp = $game->tipps->first(); @endphp
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs text-gray-500">{{ $game->kickoff_at->format('D, d M') }}</span>
                                    @if ($userTipp)
                                        <span class="text-xs px-2 py-0.5 {{ $userTipp->score > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }} rounded">
                                            +{{ $userTipp->score }} pts ({{ $userTipp->goals_home }}:{{ $userTipp->goals_visitor }})
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">No tipp</span>
                                    @endif
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        @if ($game->homeTeam)
                                            <x-flag :code="$game->homeTeam->nation->code" size="text-lg" />
                                        @endif
                                        <span class="font-medium text-sm">{{ $game->homeTeam?->nation->name }}</span>
                                    </div>
                                    <span class="font-bold">{{ $game->goals_home }} : {{ $game->goals_visitor }}</span>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-sm">{{ $game->visitorTeam?->nation->name }}</span>
                                        @if ($game->visitorTeam)
                                            <x-flag :code="$game->visitorTeam->nation->code" size="text-lg" />
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                                No finished games yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                No active tournament found.
            </div>
        @endif
    </div>
</div>
