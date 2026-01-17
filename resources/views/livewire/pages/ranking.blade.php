<?php

use App\Models\GameTipp;
use App\Models\Tournament;
use App\Models\User;
use App\Models\UserScore;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component
{
    #[Computed]
    public function tournament()
    {
        return Tournament::where('is_active', true)->first();
    }

    #[Computed]
    public function rankings()
    {
        if (!$this->tournament) {
            return collect();
        }

        // Get user scores or calculate from game tipps
        $userScores = UserScore::where('tournament_id', $this->tournament->id)
            ->with('user', 'championTeam.nation')
            ->orderByDesc('total_points')
            ->orderByDesc('game_points')
            ->get();

        if ($userScores->isEmpty()) {
            // Calculate from game tipps if no user_scores exist yet
            return $this->calculateRankingsFromTipps();
        }

        return $userScores;
    }

    private function calculateRankingsFromTipps()
    {
        return User::select('users.*')
            ->selectRaw('COALESCE(SUM(game_tipps.score), 0) as total_points')
            ->selectRaw('COUNT(game_tipps.id) as tipp_count')
            ->leftJoin('game_tipps', 'users.id', '=', 'game_tipps.user_id')
            ->leftJoin('games', function ($join) {
                $join->on('game_tipps.game_id', '=', 'games.id')
                    ->where('games.tournament_id', '=', $this->tournament->id);
            })
            ->groupBy('users.id')
            ->orderByDesc('total_points')
            ->get()
            ->map(function ($user, $index) {
                return (object) [
                    'rank' => $index + 1,
                    'user' => $user,
                    'total_points' => $user->total_points,
                    'game_points' => $user->total_points,
                    'special_points' => 0,
                    'tipp_count' => $user->tipp_count,
                    'average_score' => $user->tipp_count > 0 ? round($user->total_points / $user->tipp_count, 2) : 0,
                ];
            });
    }

    #[Computed]
    public function currentUserRank()
    {
        $rankings = $this->rankings;
        foreach ($rankings as $index => $ranking) {
            $user = $ranking->user ?? $ranking;
            if ($user->id === auth()->id()) {
                return $index + 1;
            }
        }
        return null;
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Ranking') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if ($this->tournament)
                <!-- Current User Stats -->
                @if ($this->currentUserRank)
                    <div class="mb-6 bg-gradient-to-r from-indigo-500 to-purple-600 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm opacity-80">Your current rank</p>
                                    <p class="text-4xl font-bold">#{{ $this->currentUserRank }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm opacity-80">Total points</p>
                                    @php
                                        $currentUserScore = $this->rankings->first(fn($r) => ($r->user ?? $r)->id === auth()->id());
                                    @endphp
                                    <p class="text-4xl font-bold">{{ $currentUserScore->total_points ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Ranking Table -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16">
                                    Rank
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Player
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Tipps
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Avg
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Points
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($this->rankings as $index => $ranking)
                                @php
                                    $user = $ranking->user ?? $ranking;
                                    $rank = $ranking->rank ?? ($index + 1);
                                    $isCurrentUser = $user->id === auth()->id();
                                @endphp
                                <tr class="{{ $isCurrentUser ? 'bg-indigo-50' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            @if ($rank === 1)
                                                <span class="text-2xl">🥇</span>
                                            @elseif ($rank === 2)
                                                <span class="text-2xl">🥈</span>
                                            @elseif ($rank === 3)
                                                <span class="text-2xl">🥉</span>
                                            @else
                                                <span class="text-lg font-semibold text-gray-600">{{ $rank }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-medium text-sm">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>
                                            <div class="ml-3">
                                                <p class="font-medium {{ $isCurrentUser ? 'text-indigo-600' : 'text-gray-900' }}">
                                                    {{ $user->display_name ?? $user->name }}
                                                    @if ($isCurrentUser)
                                                        <span class="text-xs text-gray-500">(You)</span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                        {{ $ranking->tipp_count ?? 0 }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                        {{ number_format($ranking->average_score ?? 0, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <span class="text-lg font-bold {{ $isCurrentUser ? 'text-indigo-600' : 'text-gray-900' }}">
                                            {{ $ranking->total_points ?? 0 }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                        No rankings available yet. Start making predictions!
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Scoring Legend -->
                <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Scoring System</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 bg-green-100 text-green-800 rounded-full flex items-center justify-center font-bold">4</span>
                            <span class="text-gray-600">Exact result</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center font-bold">3</span>
                            <span class="text-gray-600">Goal difference</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 bg-yellow-100 text-yellow-800 rounded-full flex items-center justify-center font-bold">1</span>
                            <span class="text-gray-600">Correct tendency</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 bg-gray-100 text-gray-800 rounded-full flex items-center justify-center font-bold">0</span>
                            <span class="text-gray-600">Wrong</span>
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
</div>
