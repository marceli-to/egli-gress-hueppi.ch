<?php

use App\Models\GroupMember;
use App\Models\TippGroup;
use App\Models\Tournament;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Hash;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showCreateModal = false;
    public bool $showJoinModal = false;
    public string $newGroupName = '';
    public string $newGroupPassword = '';
    public string $joinGroupCode = '';
    public string $joinGroupPassword = '';
    public ?int $selectedGroupId = null;

    #[Computed]
    public function tournament()
    {
        return Tournament::where('is_active', true)->first();
    }

    #[Computed]
    public function myGroups()
    {
        if (!$this->tournament) {
            return collect();
        }

        return TippGroup::where('tournament_id', $this->tournament->id)
            ->whereHas('members', fn($q) => $q->where('user_id', auth()->id()))
            ->with(['owner', 'members'])
            ->get();
    }

    #[Computed]
    public function publicGroups()
    {
        if (!$this->tournament) {
            return collect();
        }

        return TippGroup::where('tournament_id', $this->tournament->id)
            ->whereNull('password')
            ->whereDoesntHave('members', fn($q) => $q->where('user_id', auth()->id()))
            ->with(['owner', 'members'])
            ->get();
    }

    #[Computed]
    public function selectedGroup()
    {
        if (!$this->selectedGroupId) {
            return null;
        }

        return TippGroup::with(['members.user.userScores' => fn($q) => $q->where('tournament_id', $this->tournament->id)])
            ->find($this->selectedGroupId);
    }

    public function createGroup(): void
    {
        $this->validate([
            'newGroupName' => 'required|string|min:3|max:100',
        ]);

        $group = TippGroup::create([
            'tournament_id' => $this->tournament->id,
            'name' => $this->newGroupName,
            'password' => $this->newGroupPassword ? Hash::make($this->newGroupPassword) : null,
            'owner_user_id' => auth()->id(),
        ]);

        // Add creator as member
        GroupMember::create([
            'tipp_group_id' => $group->id,
            'user_id' => auth()->id(),
        ]);

        $this->newGroupName = '';
        $this->newGroupPassword = '';
        $this->showCreateModal = false;
        $this->selectedGroupId = $group->id;
    }

    public function joinPublicGroup(int $groupId): void
    {
        $group = TippGroup::find($groupId);
        if (!$group || $group->password) {
            return;
        }

        GroupMember::firstOrCreate([
            'tipp_group_id' => $group->id,
            'user_id' => auth()->id(),
        ]);

        $this->selectedGroupId = $group->id;
    }

    public function joinPrivateGroup(): void
    {
        $this->validate([
            'joinGroupCode' => 'required',
            'joinGroupPassword' => 'required',
        ]);

        $group = TippGroup::where('id', $this->joinGroupCode)
            ->orWhere('name', $this->joinGroupCode)
            ->first();

        if (!$group) {
            $this->addError('joinGroupCode', 'Group not found.');
            return;
        }

        if (!$group->password || !Hash::check($this->joinGroupPassword, $group->password)) {
            $this->addError('joinGroupPassword', 'Incorrect password.');
            return;
        }

        GroupMember::firstOrCreate([
            'tipp_group_id' => $group->id,
            'user_id' => auth()->id(),
        ]);

        $this->joinGroupCode = '';
        $this->joinGroupPassword = '';
        $this->showJoinModal = false;
        $this->selectedGroupId = $group->id;
    }

    public function leaveGroup(int $groupId): void
    {
        $group = TippGroup::find($groupId);
        if (!$group) return;

        // Can't leave if owner
        if ($group->owner_user_id === auth()->id()) {
            return;
        }

        GroupMember::where('tipp_group_id', $groupId)
            ->where('user_id', auth()->id())
            ->delete();

        if ($this->selectedGroupId === $groupId) {
            $this->selectedGroupId = null;
        }
    }

    public function selectGroup(int $groupId): void
    {
        $this->selectedGroupId = $groupId;
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tipp Groups') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if ($this->tournament)
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Groups List -->
                    <div class="lg:col-span-1 space-y-6">
                        <!-- My Groups -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-semibold text-gray-800">My Groups</h3>
                                <button wire:click="$set('showCreateModal', true)" class="text-sm text-indigo-600 hover:text-indigo-800">
                                    + Create
                                </button>
                            </div>
                            <div class="space-y-2">
                                @forelse ($this->myGroups as $group)
                                    <button
                                        wire:click="selectGroup({{ $group->id }})"
                                        class="w-full text-left p-3 rounded-lg transition-colors {{ $selectedGroupId === $group->id ? 'bg-indigo-100 border-indigo-300' : 'bg-gray-50 hover:bg-gray-100' }}"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium">{{ $group->name }}</span>
                                            <span class="text-xs text-gray-500">{{ $group->members->count() }} members</span>
                                        </div>
                                        @if ($group->owner_user_id === auth()->id())
                                            <span class="text-xs text-indigo-600">Owner</span>
                                        @endif
                                    </button>
                                @empty
                                    <p class="text-sm text-gray-500">You haven't joined any groups yet.</p>
                                @endforelse
                            </div>
                        </div>

                        <!-- Public Groups -->
                        @if ($this->publicGroups->isNotEmpty())
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                                <h3 class="font-semibold text-gray-800 mb-4">Public Groups</h3>
                                <div class="space-y-2">
                                    @foreach ($this->publicGroups as $group)
                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                            <div>
                                                <span class="font-medium">{{ $group->name }}</span>
                                                <span class="text-xs text-gray-500 ml-2">{{ $group->members->count() }} members</span>
                                            </div>
                                            <button wire:click="joinPublicGroup({{ $group->id }})" class="text-sm text-indigo-600 hover:text-indigo-800">
                                                Join
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Join Private Group -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <button wire:click="$set('showJoinModal', true)" class="w-full py-2 text-sm text-indigo-600 border border-dashed border-gray-300 rounded-lg hover:border-indigo-300">
                                Join Private Group
                            </button>
                        </div>
                    </div>

                    <!-- Group Details -->
                    <div class="lg:col-span-2">
                        @if ($this->selectedGroup)
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-6 border-b border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-800">{{ $this->selectedGroup->name }}</h3>
                                            <p class="text-sm text-gray-500">
                                                Created by {{ $this->selectedGroup->owner->name }}
                                                @if ($this->selectedGroup->password)
                                                    · <span class="text-yellow-600">Private</span>
                                                @else
                                                    · <span class="text-green-600">Public</span>
                                                @endif
                                            </p>
                                        </div>
                                        @if ($this->selectedGroup->owner_user_id !== auth()->id())
                                            <button
                                                wire:click="leaveGroup({{ $this->selectedGroup->id }})"
                                                wire:confirm="Leave this group?"
                                                class="text-sm text-red-600 hover:text-red-800"
                                            >
                                                Leave
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                <!-- Group Ranking -->
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Player</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Points</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @php
                                            $members = $this->selectedGroup->members->sortByDesc(fn($m) => $m->user->userScores->first()?->total_points ?? 0)->values();
                                        @endphp
                                        @foreach ($members as $index => $member)
                                            @php
                                                $score = $member->user->userScores->first();
                                                $isCurrentUser = $member->user_id === auth()->id();
                                            @endphp
                                            <tr class="{{ $isCurrentUser ? 'bg-indigo-50' : '' }}">
                                                <td class="px-6 py-4">
                                                    @if ($index === 0)
                                                        <span class="text-xl">🥇</span>
                                                    @elseif ($index === 1)
                                                        <span class="text-xl">🥈</span>
                                                    @elseif ($index === 2)
                                                        <span class="text-xl">🥉</span>
                                                    @else
                                                        <span class="text-gray-600 font-semibold">{{ $index + 1 }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="{{ $isCurrentUser ? 'font-semibold text-indigo-600' : '' }}">
                                                        {{ $member->user->display_name ?? $member->user->name }}
                                                        @if ($isCurrentUser) <span class="text-xs text-gray-500">(You)</span> @endif
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-right font-bold">
                                                    {{ $score?->total_points ?? 0 }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-12 text-center text-gray-500">
                                Select a group to view its ranking
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Create Group Modal -->
                @if ($showCreateModal)
                    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click.self="$set('showCreateModal', false)">
                        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                            <h3 class="text-lg font-semibold mb-4">Create New Group</h3>
                            <form wire:submit="createGroup" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Group Name</label>
                                    <input type="text" wire:model="newGroupName" class="w-full rounded-md border-gray-300" placeholder="My Awesome Group">
                                    @error('newGroupName') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Password (optional)</label>
                                    <input type="text" wire:model="newGroupPassword" class="w-full rounded-md border-gray-300" placeholder="Leave empty for public group">
                                    <p class="text-xs text-gray-500 mt-1">Share this password with friends to let them join.</p>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 text-gray-600">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Create</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                <!-- Join Private Group Modal -->
                @if ($showJoinModal)
                    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click.self="$set('showJoinModal', false)">
                        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                            <h3 class="text-lg font-semibold mb-4">Join Private Group</h3>
                            <form wire:submit="joinPrivateGroup" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Group Name or ID</label>
                                    <input type="text" wire:model="joinGroupCode" class="w-full rounded-md border-gray-300" placeholder="Enter group name or ID">
                                    @error('joinGroupCode') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                    <input type="password" wire:model="joinGroupPassword" class="w-full rounded-md border-gray-300">
                                    @error('joinGroupPassword') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" wire:click="$set('showJoinModal', false)" class="px-4 py-2 text-gray-600">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Join</button>
                                </div>
                            </form>
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
