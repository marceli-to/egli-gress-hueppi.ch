<?php

use App\Livewire\Actions\Logout;
use App\Models\Tournament;
use App\Models\UserScore;
use App\Models\SpecialTipp;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts.guest-home')] class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
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

        return UserScore::where('tournament_id', $this->tournament->id)
            ->with(['user', 'championTeam.nation'])
            ->orderBy('rank')
            ->orderByDesc('total_points')
            ->limit(50)
            ->get()
            ->map(function ($score, $index) {
                return (object) [
                    'rank' => $score->rank ?? ($index + 1),
                    'user' => $score->user,
                    'championTeam' => $score->championTeam,
                    'total_points' => $score->total_points,
                ];
            });
    }
}; ?>

<div class="min-h-screen bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <span class="text-xl font-bold text-gray-800">egli-gress.ch</span>
                </div>
                <div class="flex items-center gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">Login</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">Register</a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Left Column: Welcome Section -->
                <div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        @auth
                            <h1 class="text-2xl font-bold text-gray-800 mb-4">
                                Hallo {{ auth()->user()->display_name ?? auth()->user()->name }}
                            </h1>
                            <p class="text-gray-600 mb-6">
                                Willkommen zuruck beim Tippspiel! Tippe jetzt auf die kommenden Spiele und sammle Punkte.
                            </p>
                            <div class="flex items-center gap-4">
                                <a href="{{ route('games') }}" wire:navigate
                                   class="inline-flex items-center gap-2 px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-full hover:bg-gray-800 transition-colors">
                                    Zu den Spielen
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </a>
                                <button wire:click="logout" class="text-sm text-gray-500 hover:text-gray-700">
                                    Logout
                                </button>
                            </div>
                        @else
                            <h1 class="text-2xl font-bold text-gray-800 mb-4">
                                Herzlich willkommen bei egli-gress.ch
                            </h1>
                            <p class="text-gray-600 mb-6">
                                Das offizielle Tippspiel fur Fussball-Weltmeisterschaften und Europameisterschaften.
                                Tippe auf alle Spiele, sammle Punkte und messe dich mit anderen Spielern.
                            </p>
                            <a href="{{ route('login') }}" wire:navigate
                               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-full hover:bg-gray-800 transition-colors">
                                Login
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        @endauth
                    </div>
                </div>

                <!-- Right Column: Overall Ranking Table -->
                <div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-800">Gesamtrangliste</h2>
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
                                    <tr class="{{ auth()->check() && $ranking->user->id === auth()->id() ? 'bg-indigo-50' : '' }}">
                                        <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                            {{ $ranking->rank }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-900">
                                            {{ $ranking->user->display_name ?? $ranking->user->name }}
                                            @if (auth()->check() && $ranking->user->id === auth()->id())
                                                <span class="text-xs text-gray-500">(Du)</span>
                                            @endif
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
            </div>
        </div>
    </div>
</div>
