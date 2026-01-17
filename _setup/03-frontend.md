# Frontend - Vue.js to Livewire Migration

## Original Vue.js Structure

```
tippspiel-fe/src/js/
├── views/
│   ├── Home.vue
│   ├── Games.vue
│   ├── BonusTips.vue
│   ├── Ranking.vue
│   ├── Profile.vue
│   ├── Dashboard.vue
│   ├── Login.vue
│   └── Contact.vue
├── components/
│   ├── base/
│   │   ├── header.vue
│   │   └── footer.vue
│   ├── ranking/
│   │   ├── RankingNav.vue
│   │   └── UserScoreChart.vue
│   └── utilities/
│       ├── Game.vue
│       ├── GameResult.vue
│       ├── GroupRanking.vue
│       └── Loader.vue
├── stores/
│   └── user.js (Pinia)
├── router/
│   └── index.js
└── plugins/
    └── keycloak.js
```

## Component Mapping

### Page Components (Views -> Livewire Full-Page)

| Vue Component | Livewire Component | Description |
|---------------|-------------------|-------------|
| `Home.vue` | `Pages/Home.php` | Dashboard with upcoming games |
| `Games.vue` | `Pages/Games.php` | Game list with tipp forms |
| `BonusTips.vue` | `Pages/SpecialTipps.php` | Special predictions |
| `Ranking.vue` | `Pages/Ranking.php` | Global/group rankings |
| `Profile.vue` | `Pages/Profile.php` | User profile & stats |
| `Dashboard.vue` | `Pages/Dashboard.php` | Admin dashboard |

### Reusable Components (-> Livewire/Blade)

| Vue Component | Livewire/Blade | Description |
|---------------|----------------|-------------|
| `Game.vue` | `Livewire\Games\GameCard.php` | Single game with tipp form |
| `GameResult.vue` | `Blade: game-result.blade.php` | Display game result |
| `GroupRanking.vue` | `Livewire\Ranking\GroupStandings.php` | Team standings in group |
| `UserScoreChart.vue` | `Blade + Alpine: score-chart.blade.php` | Score progression chart |
| `RankingNav.vue` | `Blade: ranking-nav.blade.php` | Ranking filter navigation |
| `Loader.vue` | `Blade: loader.blade.php` | Loading spinner |

## Livewire Component Examples

### GameCard.php (replaces Game.vue)

```php
<?php

namespace App\Livewire\Games;

use App\Models\Game;
use App\Models\GameTipp;
use Livewire\Component;

class GameCard extends Component
{
    public Game $game;
    public ?int $goalsHome = null;
    public ?int $goalsVisitor = null;
    public ?int $penaltyWinner = null;
    public bool $editing = false;
    public bool $saved = false;

    public function mount(Game $game)
    {
        $this->game = $game->load(['homeTeam.nation', 'visitorTeam.nation']);

        if ($tipp = $game->userTipp) {
            $this->goalsHome = $tipp->goals_home;
            $this->goalsVisitor = $tipp->goals_visitor;
            $this->penaltyWinner = $tipp->penalty_winner_team_id;
        }
    }

    public function saveTipp()
    {
        if (!$this->game->canBeTipped()) {
            return;
        }

        $this->validate([
            'goalsHome' => 'required|integer|min:0|max:20',
            'goalsVisitor' => 'required|integer|min:0|max:20',
        ]);

        GameTipp::updateOrCreate(
            ['user_id' => auth()->id(), 'game_id' => $this->game->id],
            [
                'goals_home' => $this->goalsHome,
                'goals_visitor' => $this->goalsVisitor,
                'penalty_winner_team_id' => $this->penaltyWinner,
            ]
        );

        $this->saved = true;
        $this->editing = false;
    }

    public function render()
    {
        return view('livewire.games.game-card');
    }
}
```

### game-card.blade.php

```blade
<div class="game-card border rounded-lg p-4 mb-4">
    {{-- Game Header --}}
    <div class="flex justify-between items-center mb-3">
        <span class="text-sm text-gray-500">
            {{ $game->kickoff_at->format('d.m.Y H:i') }}
        </span>
        <span class="text-xs px-2 py-1 bg-gray-100 rounded">
            {{ $game->location->city }}
        </span>
    </div>

    {{-- Teams --}}
    <div class="flex items-center justify-between">
        {{-- Home Team --}}
        <div class="flex items-center gap-2 w-1/3">
            <img src="{{ asset('flags/' . $game->homeTeam->nation->code . '.svg') }}"
                 class="w-6 h-4" alt="">
            <span class="font-medium">{{ $game->homeTeam->nation->name }}</span>
        </div>

        {{-- Score / Tipp Input --}}
        <div class="flex items-center gap-2">
            @if($game->canBeTipped() && !$game->is_finished)
                <input type="number"
                       wire:model="goalsHome"
                       class="w-12 text-center border rounded"
                       min="0" max="20">
                <span class="text-gray-400">:</span>
                <input type="number"
                       wire:model="goalsVisitor"
                       class="w-12 text-center border rounded"
                       min="0" max="20">
            @elseif($game->is_finished)
                <span class="text-2xl font-bold">
                    {{ $game->goals_home }} : {{ $game->goals_visitor }}
                </span>
            @else
                <span class="text-gray-400">vs</span>
            @endif
        </div>

        {{-- Visitor Team --}}
        <div class="flex items-center gap-2 justify-end w-1/3">
            <span class="font-medium">{{ $game->visitorTeam->nation->name }}</span>
            <img src="{{ asset('flags/' . $game->visitorTeam->nation->code . '.svg') }}"
                 class="w-6 h-4" alt="">
        </div>
    </div>

    {{-- User's Tipp (if game finished) --}}
    @if($game->is_finished && $game->userTipp)
        <div class="mt-3 pt-3 border-t flex justify-between items-center">
            <span class="text-sm text-gray-500">
                Your tipp: {{ $goalsHome }} : {{ $goalsVisitor }}
            </span>
            <span class="font-bold text-lg
                @if($game->userTipp->score == 4) text-green-600
                @elseif($game->userTipp->score >= 1) text-yellow-600
                @else text-red-600 @endif">
                +{{ $game->userTipp->score }}
            </span>
        </div>
    @endif

    {{-- Save Button --}}
    @if($game->canBeTipped())
        <div class="mt-3 flex justify-end">
            <button wire:click="saveTipp"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Save Tipp
            </button>
        </div>
    @endif

    {{-- Saved Notification --}}
    @if($saved)
        <div class="mt-2 text-sm text-green-600"
             x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 2000)"
             x-show="show">
            Tipp saved!
        </div>
    @endif
</div>
```

### GroupStandings.php (replaces GroupRanking.vue)

```php
<?php

namespace App\Livewire\Ranking;

use App\Models\Team;
use Livewire\Component;

class GroupStandings extends Component
{
    public string $groupName;

    public function render()
    {
        $teams = Team::query()
            ->whereHas('tournament', fn($q) => $q->where('is_active', true))
            ->where('group_name', $this->groupName)
            ->with('nation')
            ->orderByDesc('points')
            ->orderByDesc(\DB::raw('goals_for - goals_against'))
            ->orderByDesc('goals_for')
            ->get();

        return view('livewire.ranking.group-standings', [
            'teams' => $teams,
        ]);
    }
}
```

## Blade Layout Structure

### layouts/app.blade.php

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - @yield('title')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50">
    <x-header />

    <main class="container mx-auto px-4 py-8">
        {{ $slot }}
    </main>

    <x-footer />

    @livewireScripts
</body>
</html>
```

### components/header.blade.php

```blade
<header class="bg-white shadow">
    <nav class="container mx-auto px-4 py-4 flex justify-between items-center">
        <a href="{{ route('home') }}" class="text-xl font-bold">
            Tippspiel
        </a>

        <div class="flex items-center gap-6">
            <a href="{{ route('games') }}"
               class="hover:text-blue-600 {{ request()->routeIs('games*') ? 'text-blue-600' : '' }}">
                Games
            </a>
            <a href="{{ route('ranking') }}"
               class="hover:text-blue-600 {{ request()->routeIs('ranking*') ? 'text-blue-600' : '' }}">
                Ranking
            </a>

            @auth
                <a href="{{ route('profile') }}"
                   class="hover:text-blue-600 {{ request()->routeIs('profile') ? 'text-blue-600' : '' }}">
                    Profile
                </a>

                @if(auth()->user()->is_admin)
                    <a href="{{ route('admin.games') }}" class="text-red-600 hover:text-red-700">
                        Admin
                    </a>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-gray-600 hover:text-gray-800">
                        Logout
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-700">
                    Login
                </a>
            @endauth
        </div>
    </nav>
</header>
```

## Alpine.js for Interactivity

For simple UI interactions that don't need server round-trips:

### Dropdown Menu

```blade
<div x-data="{ open: false }" class="relative">
    <button @click="open = !open" class="flex items-center gap-1">
        {{ auth()->user()->name }}
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
        </svg>
    </button>

    <div x-show="open"
         @click.away="open = false"
         class="absolute right-0 mt-2 w-48 bg-white rounded shadow-lg">
        <a href="{{ route('profile') }}" class="block px-4 py-2 hover:bg-gray-100">
            Profile
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full text-left px-4 py-2 hover:bg-gray-100">
                Logout
            </button>
        </form>
    </div>
</div>
```

### Score Input with Validation

```blade
<div x-data="{
    home: @entangle('goalsHome'),
    visitor: @entangle('goalsVisitor'),
    validate(value) {
        return value >= 0 && value <= 20;
    }
}">
    <input type="number"
           x-model="home"
           @input="if(!validate($event.target.value)) home = 0"
           class="w-12 text-center border rounded">
    <span>:</span>
    <input type="number"
           x-model="visitor"
           @input="if(!validate($event.target.value)) visitor = 0"
           class="w-12 text-center border rounded">
</div>
```

## Charts (ApexCharts -> Chart.js or ApexCharts)

The original uses ApexCharts for score progression. Options:

### Option 1: Keep ApexCharts with Alpine

```blade
<div x-data="{
    chart: null,
    init() {
        this.chart = new ApexCharts(this.$refs.chart, {
            series: [{
                name: 'Points',
                data: @json($scoreHistory->pluck('points'))
            }],
            xaxis: {
                categories: @json($scoreHistory->pluck('game_day'))
            },
            chart: { type: 'line', height: 300 }
        });
        this.chart.render();
    }
}" x-init="init()">
    <div x-ref="chart"></div>
</div>
```

### Option 2: Livewire Charts Package

```php
// Using asantibanez/livewire-charts
use Asantibanez\LivewireCharts\Models\LineChartModel;

public function render()
{
    $chart = (new LineChartModel())
        ->setTitle('Score Progression')
        ->addPoint('Day 1', 4)
        ->addPoint('Day 2', 8)
        ->addPoint('Day 3', 15);

    return view('livewire.charts.score-chart', ['chart' => $chart]);
}
```

## CSS Migration

The original uses SASS. Options for Livewire:

1. **Tailwind CSS** (recommended) - Utility-first, works great with Livewire
2. **Keep SASS** - Can continue using existing styles
3. **Bootstrap** - If familiar, but heavier

### Tailwind Configuration

```js
// tailwind.config.js
export default {
    content: [
        './resources/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                primary: '#1a56db',
                secondary: '#6b7280',
            }
        }
    }
}
```

## i18n Migration

Original uses `vue-i18n`. Laravel alternative:

```php
// resources/lang/en/messages.php
return [
    'games' => 'Games',
    'ranking' => 'Ranking',
    'tipp_saved' => 'Your prediction has been saved!',
    'game_started' => 'Game has already started',
];

// In Blade
{{ __('messages.games') }}
```

## Key Differences: Vue vs Livewire

| Aspect | Vue.js | Livewire |
|--------|--------|----------|
| Reactivity | Client-side | Server-side (wire:model) |
| State | Pinia/Vuex | Component properties |
| API Calls | Axios | Direct Eloquent |
| Routing | Vue Router | Laravel routes |
| Lifecycle | mounted/updated | mount/hydrate |
| Events | $emit | $dispatch / Livewire events |
