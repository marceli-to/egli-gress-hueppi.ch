<?php

use App\Models\SpecialTipp;
use App\Models\SpecialTippSpec;
use App\Models\Team;
use App\Models\Tournament;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $editingSpecId = null;
    public ?int $selectedTeamId = null;
    public ?int $predictedValue = null;

    #[Computed]
    public function tournament()
    {
        return Tournament::where('is_active', true)->first();
    }

    #[Computed]
    public function specialTippSpecs()
    {
        if (!$this->tournament) {
            return collect();
        }

        return SpecialTippSpec::where('tournament_id', $this->tournament->id)
            ->with(['tipps' => fn($q) => $q->where('user_id', auth()->id())->with('predictedTeam.nation')])
            ->get();
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

    public function canEdit(): bool
    {
        // Can only edit special tipps before tournament starts (first game)
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
        } else {
            $this->selectedTeamId = null;
            $this->predictedValue = null;
        }
    }

    public function cancelEdit(): void
    {
        $this->editingSpecId = null;
        $this->selectedTeamId = null;
        $this->predictedValue = null;
    }

    public function saveTipp(): void
    {
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
        } else {
            $this->validate([
                'selectedTeamId' => 'required|exists:teams,id',
            ]);
        }

        SpecialTipp::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'special_tipp_spec_id' => $spec->id,
            ],
            [
                'predicted_team_id' => $spec->type !== 'TOTAL_GOALS' ? $this->selectedTeamId : null,
                'predicted_value' => $spec->type === 'TOTAL_GOALS' ? $this->predictedValue : null,
            ]
        );

        $this->cancelEdit();
    }

    public function getSpecLabel(SpecialTippSpec $spec): string
    {
        return match ($spec->name) {
            'WINNER_WORLDCUP' => 'World Cup Champion',
            'WINNER_GROUP_A' => 'Group A Winner',
            'WINNER_GROUP_B' => 'Group B Winner',
            'WINNER_GROUP_C' => 'Group C Winner',
            'WINNER_GROUP_D' => 'Group D Winner',
            'WINNER_GROUP_E' => 'Group E Winner',
            'WINNER_GROUP_F' => 'Group F Winner',
            'WINNER_GROUP_G' => 'Group G Winner',
            'WINNER_GROUP_H' => 'Group H Winner',
            'FINAL_RANKING_CH' => 'Switzerland Final Ranking',
            'TOTAL_GOALS_CH' => 'Switzerland Total Goals',
            default => $spec->name,
        };
    }

    public function getTeamsForSpec(SpecialTippSpec $spec)
    {
        // For group winners, only show teams from that group
        if (str_starts_with($spec->name, 'WINNER_GROUP_')) {
            $group = substr($spec->name, -1);
            return $this->teamsByGroup[$group] ?? collect();
        }

        // For tournament champion, show all teams
        return $this->teams;
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Special Tipps') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if ($this->tournament)
                @if (!$this->canEdit())
                    <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-yellow-800">
                        The tournament has started. Special tipps can no longer be changed.
                    </div>
                @endif

                <!-- Tournament Champion -->
                @php $championSpec = $this->specialTippSpecs->firstWhere('name', 'WINNER_WORLDCUP'); @endphp
                @if ($championSpec)
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Tournament Champion</h3>
                        <div class="bg-gradient-to-r from-yellow-400 to-yellow-500 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                @if ($editingSpecId === $championSpec->id)
                                    <form wire:submit="saveTipp" class="space-y-4">
                                        <div class="grid grid-cols-4 sm:grid-cols-8 gap-2">
                                            @foreach ($this->teams as $team)
                                                <label class="cursor-pointer">
                                                    <input type="radio" wire:model="selectedTeamId" value="{{ $team->id }}" class="sr-only peer">
                                                    <div class="p-2 bg-white/80 rounded-lg text-center peer-checked:bg-white peer-checked:ring-2 peer-checked:ring-yellow-600 hover:bg-white transition-colors">
                                                        <x-flag :code="$team->nation->code" size="text-lg" />
                                                        <span class="text-xs font-medium block">{{ $team->nation->name }}</span>
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('selectedTeamId') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                                        <div class="flex gap-2">
                                            <button type="button" wire:click="cancelEdit" class="px-4 py-2 text-sm bg-white/50 rounded hover:bg-white/80">Cancel</button>
                                            <button type="submit" class="px-4 py-2 text-sm bg-yellow-600 text-white rounded hover:bg-yellow-700">Save</button>
                                        </div>
                                    </form>
                                @else
                                    @php $championTipp = $championSpec->tipps->first(); @endphp
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-yellow-900 text-sm font-medium">Your prediction</p>
                                            <p class="text-2xl font-bold text-yellow-900 flex items-center gap-2">
                                                @if ($championTipp?->predictedTeam)
                                                    <x-flag :code="$championTipp->predictedTeam->nation->code" />
                                                @endif
                                                {{ $championTipp?->predictedTeam?->nation->name ?? 'Not set' }}
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-yellow-900 text-sm">Points if correct</p>
                                            <p class="text-3xl font-bold text-yellow-900">{{ $championSpec->value }}</p>
                                        </div>
                                    </div>
                                    @if ($this->canEdit())
                                        <button wire:click="editTipp({{ $championSpec->id }})" class="mt-4 px-4 py-2 text-sm bg-white/50 rounded hover:bg-white/80">
                                            {{ $championTipp ? 'Change' : 'Select Champion' }}
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Group Winners -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Group Winners</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        @foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $group)
                            @php
                                $spec = $this->specialTippSpecs->firstWhere('name', 'WINNER_GROUP_' . $group);
                                $tipp = $spec?->tipps->first();
                                $groupTeams = $this->teamsByGroup[$group] ?? collect();
                            @endphp
                            @if ($spec)
                                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                    <div class="p-4">
                                        <div class="flex items-center justify-between mb-3">
                                            <span class="font-semibold text-gray-800">Group {{ $group }}</span>
                                            <span class="text-sm text-gray-500">{{ $spec->value }} pts</span>
                                        </div>

                                        @if ($editingSpecId === $spec->id)
                                            <form wire:submit="saveTipp" class="space-y-2">
                                                @foreach ($groupTeams as $team)
                                                    <label class="flex items-center gap-2 cursor-pointer p-2 rounded hover:bg-gray-50">
                                                        <input type="radio" wire:model="selectedTeamId" value="{{ $team->id }}" class="text-indigo-600">
                                                        <x-flag :code="$team->nation->code" size="text-lg" />
                                                        <span class="text-sm">{{ $team->nation->name }}</span>
                                                    </label>
                                                @endforeach
                                                @error('selectedTeamId') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                                                <div class="flex gap-2 pt-2">
                                                    <button type="button" wire:click="cancelEdit" class="flex-1 px-2 py-1 text-xs text-gray-600 border rounded hover:bg-gray-50">Cancel</button>
                                                    <button type="submit" class="flex-1 px-2 py-1 text-xs bg-gray-900 text-white rounded hover:bg-gray-800">Save</button>
                                                </div>
                                            </form>
                                        @else
                                            <div class="text-center py-2">
                                                <p class="font-medium {{ $tipp ? 'text-gray-900' : 'text-gray-400' }} flex items-center justify-center gap-2">
                                                    @if ($tipp?->predictedTeam)
                                                        <x-flag :code="$tipp->predictedTeam->nation->code" size="text-lg" />
                                                    @endif
                                                    {{ $tipp?->predictedTeam?->nation->name ?? 'Not set' }}
                                                </p>
                                            </div>
                                            @if ($this->canEdit())
                                                <button wire:click="editTipp({{ $spec->id }})" class="w-full mt-2 px-2 py-1 text-xs text-indigo-600 border border-dashed border-gray-300 rounded hover:border-indigo-300">
                                                    {{ $tipp ? 'Change' : 'Select' }}
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                <!-- Switzerland Specific -->
                @php
                    $swissRankingSpec = $this->specialTippSpecs->firstWhere('name', 'FINAL_RANKING_CH');
                    $swissGoalsSpec = $this->specialTippSpecs->firstWhere('name', 'TOTAL_GOALS_CH');
                @endphp
                @if ($swissRankingSpec || $swissGoalsSpec)
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Switzerland Special</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @if ($swissGoalsSpec)
                                @php $swissGoalsTipp = $swissGoalsSpec->tipps->first(); @endphp
                                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                    <div class="p-4">
                                        <div class="flex items-center justify-between mb-3">
                                            <span class="font-semibold text-gray-800">Total Goals by Switzerland</span>
                                            <span class="text-sm text-gray-500">{{ $swissGoalsSpec->value }} pts</span>
                                        </div>

                                        @if ($editingSpecId === $swissGoalsSpec->id)
                                            <form wire:submit="saveTipp" class="space-y-3">
                                                <input
                                                    type="number"
                                                    wire:model="predictedValue"
                                                    min="0"
                                                    max="50"
                                                    class="w-full text-center rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    placeholder="Number of goals"
                                                >
                                                @error('predictedValue') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                                                <div class="flex gap-2">
                                                    <button type="button" wire:click="cancelEdit" class="flex-1 px-2 py-1 text-xs text-gray-600 border rounded hover:bg-gray-50">Cancel</button>
                                                    <button type="submit" class="flex-1 px-2 py-1 text-xs bg-gray-900 text-white rounded hover:bg-gray-800">Save</button>
                                                </div>
                                            </form>
                                        @else
                                            <div class="text-center py-2">
                                                <p class="text-2xl font-bold {{ $swissGoalsTipp ? 'text-gray-900' : 'text-gray-400' }}">
                                                    {{ $swissGoalsTipp?->predicted_value ?? '-' }}
                                                </p>
                                                <p class="text-xs text-gray-500">goals</p>
                                            </div>
                                            @if ($this->canEdit())
                                                <button wire:click="editTipp({{ $swissGoalsSpec->id }})" class="w-full mt-2 px-2 py-1 text-xs text-indigo-600 border border-dashed border-gray-300 rounded hover:border-indigo-300">
                                                    {{ $swissGoalsTipp ? 'Change' : 'Predict' }}
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                    No active tournament found.
                </div>
            @endif
        </div>
    </div>
</div>
