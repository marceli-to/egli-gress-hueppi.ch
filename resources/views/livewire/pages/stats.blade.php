<?php

use App\Models\GameTipp;
use App\Models\Tournament;
use App\Models\User;
use App\Models\UserScoreHistory;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts.app')] class extends Component
{
    public array $compareUserIds = [];

    #[Computed]
    public function tournament()
    {
        return Tournament::where('is_active', true)->first();
    }

    #[Computed]
    public function users()
    {
        return User::orderBy('name')->get();
    }

    #[Computed]
    public function rankProgressionData()
    {
        if (!$this->tournament) {
            return null;
        }

        $history = UserScoreHistory::where('user_id', auth()->id())
            ->where('tournament_id', $this->tournament->id)
            ->orderBy('game_day')
            ->get();

        if ($history->isEmpty()) {
            return null;
        }

        return [
            'labels' => $history->pluck('game_day')->toArray(),
            'data' => $history->pluck('rank')->toArray()
        ];
    }

    #[Computed]
    public function pointsProgressionData()
    {
        if (!$this->tournament) {
            return null;
        }

        $history = UserScoreHistory::where('user_id', auth()->id())
            ->where('tournament_id', $this->tournament->id)
            ->orderBy('game_day')
            ->get();

        if ($history->isEmpty()) {
            return null;
        }

        return [
            'labels' => $history->pluck('game_day')->toArray(),
            'data' => $history->pluck('points')->toArray()
        ];
    }

    #[Computed]
    public function comparisonData()
    {
        if (!$this->tournament || empty($this->compareUserIds)) {
            return null;
        }

        $userIds = array_unique(array_merge([auth()->id()], $this->compareUserIds));
        $datasets = [];
        $labels = [];
        $colors = ['#6366F1', '#EC4899', '#F59E0B', '#10B981', '#EF4444'];

        $colorIndex = 0;
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (!$user) continue;

            $history = UserScoreHistory::where('user_id', $userId)
                ->where('tournament_id', $this->tournament->id)
                ->orderBy('game_day')
                ->get();

            if ($history->isNotEmpty()) {
                $datasets[] = [
                    'label' => $user->name,
                    'data' => $history->pluck('points')->toArray(),
                    'borderColor' => $colors[$colorIndex % count($colors)],
                    'borderWidth' => 1.5,
                    'tension' => 0.3,
                    'fill' => false,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4
                ];

                if (empty($labels)) {
                    $labels = $history->pluck('game_day')->toArray();
                }
                $colorIndex++;
            }
        }

        if (empty($datasets)) {
            return null;
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets
        ];
    }

    #[Computed]
    public function tippAccuracyData()
    {
        if (!$this->tournament) {
            return null;
        }

        $tipps = GameTipp::where('user_id', auth()->id())
            ->whereHas('game', fn($q) => $q->where('tournament_id', $this->tournament->id)->where('is_finished', true))
            ->with('game')
            ->get();

        if ($tipps->isEmpty()) {
            return null;
        }

        // Categorize by accuracy level
        $perfect = $tipps->filter(fn($t) => $t->is_goals_home_correct && $t->is_goals_visitor_correct)->count();
        $goodDiff = $tipps->filter(fn($t) => $t->is_tendency_correct && $t->is_difference_correct && !($t->is_goals_home_correct && $t->is_goals_visitor_correct))->count();
        $tendencyOnly = $tipps->filter(fn($t) => $t->is_tendency_correct && !$t->is_difference_correct)->count();
        $wrong = $tipps->filter(fn($t) => !$t->is_tendency_correct)->count();

        $data = [];
        $labels = [];
        $colors = [];

        if ($perfect > 0) { $data[] = $perfect; $labels[] = 'Exakt'; $colors[] = '#10B981'; }
        if ($goodDiff > 0) { $data[] = $goodDiff; $labels[] = 'Differenz'; $colors[] = '#3B82F6'; }
        if ($tendencyOnly > 0) { $data[] = $tendencyOnly; $labels[] = 'Tendenz'; $colors[] = '#F59E0B'; }
        if ($wrong > 0) { $data[] = $wrong; $labels[] = 'Falsch'; $colors[] = '#EF4444'; }

        return [
            'data' => $data,
            'labels' => $labels,
            'colors' => $colors
        ];
    }

    #[Computed]
    public function tippStats()
    {
        if (!$this->tournament) {
            return null;
        }

        $tipps = GameTipp::where('user_id', auth()->id())
            ->whereHas('game', fn($q) => $q->where('tournament_id', $this->tournament->id)->where('is_finished', true))
            ->get();

        if ($tipps->isEmpty()) {
            return null;
        }

        $total = $tipps->count();
        $totalPoints = $tipps->sum('score');

        // Categorize by accuracy level
        $perfect = $tipps->filter(fn($t) => $t->is_goals_home_correct && $t->is_goals_visitor_correct)->count();
        $goodDiff = $tipps->filter(fn($t) => $t->is_tendency_correct && $t->is_difference_correct && !($t->is_goals_home_correct && $t->is_goals_visitor_correct))->count();
        $tendencyOnly = $tipps->filter(fn($t) => $t->is_tendency_correct && !$t->is_difference_correct)->count();
        $wrong = $tipps->filter(fn($t) => !$t->is_tendency_correct)->count();

        return (object) [
            'total' => $total,
            'total_points' => $totalPoints,
            'avg_points' => round($totalPoints / $total, 1),
            'perfect' => $perfect,
            'good_diff' => $goodDiff,
            'tendency' => $tendencyOnly,
            'wrong' => $wrong,
            'perfect_pct' => round($perfect / $total * 100, 1),
            'good_diff_pct' => round($goodDiff / $total * 100, 1),
            'tendency_pct' => round($tendencyOnly / $total * 100, 1),
            'wrong_pct' => round($wrong / $total * 100, 1),
        ];
    }

    public function toggleCompareUser(int $userId): void
    {
        if (in_array($userId, $this->compareUserIds)) {
            $this->compareUserIds = array_values(array_diff($this->compareUserIds, [$userId]));
        } else {
            if (count($this->compareUserIds) < 4) {
                $this->compareUserIds[] = $userId;
            }
        }
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Statistiken') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if ($this->tournament)
                <!-- Personal Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Rank Progression -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Rang-Verlauf</h3>
                        @if ($this->rankProgressionData)
                            <div
                                x-data="{
                                    init() {
                                        new Chart(this.$refs.canvas, {
                                            type: 'line',
                                            data: {
                                                labels: {{ json_encode($this->rankProgressionData['labels']) }},
                                                datasets: [{
                                                    label: 'Rank',
                                                    data: {{ json_encode($this->rankProgressionData['data']) }},
                                                    borderColor: 'rgba(99, 102, 241, 0.5)',
                                                    borderWidth: 1.5,
                                                    tension: 0.3,
                                                    fill: false,
                                                    pointRadius: 0,
                                                    pointHoverRadius: 4
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                scales: {
                                                    y: { reverse: true, beginAtZero: false, title: { display: true, text: 'Rang' }, grid: { display: false } },
                                                    x: { title: { display: true, text: 'Spieltag' }, grid: { display: false } }
                                                },
                                                plugins: { legend: { display: false } }
                                            }
                                        });
                                    }
                                }"
                            >
                                <canvas x-ref="canvas"></canvas>
                            </div>
                            <p class="text-xs text-gray-500 mt-2 text-center">Niedriger ist besser</p>
                        @else
                            <div class="h-64 flex items-center justify-center text-gray-400">
                                Noch keine Daten. Erst Spiele tippen!
                            </div>
                        @endif
                    </div>

                    <!-- Points Progression -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Punkte-Verlauf</h3>
                        @if ($this->pointsProgressionData)
                            <div
                                x-data="{
                                    init() {
                                        new Chart(this.$refs.canvas, {
                                            type: 'line',
                                            data: {
                                                labels: {{ json_encode($this->pointsProgressionData['labels']) }},
                                                datasets: [{
                                                    label: 'Points',
                                                    data: {{ json_encode($this->pointsProgressionData['data']) }},
                                                    borderColor: 'rgba(16, 185, 129, 0.5)',
                                                    borderWidth: 1.5,
                                                    tension: 0.3,
                                                    fill: false,
                                                    pointRadius: 0,
                                                    pointHoverRadius: 4
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                scales: {
                                                    y: { beginAtZero: true, title: { display: true, text: 'Punkte' }, grid: { display: false } },
                                                    x: { title: { display: true, text: 'Spieltag' }, grid: { display: false } }
                                                },
                                                plugins: { legend: { display: false } }
                                            }
                                        });
                                    }
                                }"
                            >
                                <canvas x-ref="canvas"></canvas>
                            </div>
                            <p class="text-xs text-gray-500 mt-2 text-center">Kumulierte Punkte pro Spieltag</p>
                        @else
                            <div class="h-64 flex items-center justify-center text-gray-400">
                                Noch keine Daten. Erst Spiele tippen!
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Tipp Accuracy -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Tipp-Genauigkeit</h3>
                        @if ($this->tippAccuracyData)
                            <div
                                x-data="{
                                    init() {
                                        new Chart(this.$refs.canvas, {
                                            type: 'doughnut',
                                            data: {
                                                labels: {{ json_encode($this->tippAccuracyData['labels']) }},
                                                datasets: [{
                                                    data: {{ json_encode($this->tippAccuracyData['data']) }},
                                                    backgroundColor: {{ json_encode($this->tippAccuracyData['colors']) }}
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                plugins: {
                                                    legend: { position: 'bottom' }
                                                }
                                            }
                                        });
                                    }
                                }"
                            >
                                <canvas x-ref="canvas"></canvas>
                            </div>
                        @else
                            <div class="h-64 flex items-center justify-center text-gray-400">
                                Noch keine abgeschlossenen Spiele mit Tipps.
                            </div>
                        @endif
                    </div>

                    <!-- Tipp Stats Details -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Tipp-Übersicht</h3>
                        @if ($this->tippStats)
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Tipps gesamt</span>
                                    <span class="font-bold text-xl">{{ $this->tippStats->total }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Punkte gesamt</span>
                                    <span class="font-bold text-xl">{{ $this->tippStats->total_points }}</span>
                                </div>
                                <div class="flex items-center justify-between pb-4 border-b">
                                    <span class="text-gray-600">Durchschnitt pro Tipp</span>
                                    <span class="font-bold text-xl">{{ $this->tippStats->avg_points }}</span>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-green-600 font-medium">Exakt (beide Tore richtig)</span>
                                            <span>{{ $this->tippStats->perfect }} ({{ $this->tippStats->perfect_pct }}%)</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-green-500 h-2 rounded-full" style="width: {{ $this->tippStats->perfect_pct }}%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-blue-600 font-medium">Differenz (Tendenz + Diff richtig)</span>
                                            <span>{{ $this->tippStats->good_diff }} ({{ $this->tippStats->good_diff_pct }}%)</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $this->tippStats->good_diff_pct }}%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-yellow-600 font-medium">Tendenz (nur Tendenz richtig)</span>
                                            <span>{{ $this->tippStats->tendency }} ({{ $this->tippStats->tendency_pct }}%)</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-yellow-500 h-2 rounded-full" style="width: {{ $this->tippStats->tendency_pct }}%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-red-600 font-medium">Falsch (Tendenz falsch)</span>
                                            <span>{{ $this->tippStats->wrong }} ({{ $this->tippStats->wrong_pct }}%)</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-red-500 h-2 rounded-full" style="width: {{ $this->tippStats->wrong_pct }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="h-64 flex items-center justify-center text-gray-400">
                                Noch keine abgeschlossenen Spiele mit Tipps.
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Comparison Chart -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Vergleich mit anderen</h3>

                    <!-- User Selection -->
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-2">Wähle bis zu 4 Spieler zum Vergleichen (Du bist immer dabei):</p>
                        <div class="flex flex-wrap gap-2 max-h-32 overflow-y-auto">
                            @foreach ($this->users->take(50) as $user)
                                @if ($user->id !== auth()->id())
                                    <button
                                        wire:click="toggleCompareUser({{ $user->id }})"
                                        class="px-3 py-1 text-sm rounded-full transition-colors {{ in_array($user->id, $compareUserIds) ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                                    >
                                        {{ $user->name }}
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    @if (!empty($compareUserIds) && $this->comparisonData)
                        <div
                            wire:key="comparison-{{ implode('-', $compareUserIds) }}"
                            x-data="{
                                init() {
                                    new Chart(this.$refs.canvas, {
                                        type: 'line',
                                        data: {
                                            labels: {{ json_encode($this->comparisonData['labels']) }},
                                            datasets: {{ json_encode($this->comparisonData['datasets']) }}
                                        },
                                        options: {
                                            responsive: true,
                                            scales: {
                                                y: { beginAtZero: true, title: { display: true, text: 'Punkte' } },
                                                x: { title: { display: true, text: 'Spieltag' } }
                                            },
                                            plugins: {
                                                legend: { position: 'top' }
                                            }
                                        }
                                    });
                                }
                            }"
                        >
                            <canvas x-ref="canvas"></canvas>
                        </div>
                    @else
                        <div class="h-64 flex items-center justify-center text-gray-400">
                            Wähle Spieler oben um Punkte-Verläufe zu vergleichen.
                        </div>
                    @endif
                </div>
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                    Kein aktives Turnier gefunden.
                </div>
            @endif
        </div>
    </div>
</div>
