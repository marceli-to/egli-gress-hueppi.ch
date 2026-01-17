# Performance Optimization Strategy

## Overview

This document outlines performance optimizations for handling high traffic (100+ concurrent users) on the Tippspiel application.

---

## Critical Issues

### 1. N+1 Query Problem in Score Calculation

**File:** `app/Services/ScoreCalculationService.php`

**Current (Line 218):**
```php
$users = User::all();  // Loads ALL users
foreach ($users as $user) {
    $this->calculateUserScore($user, $tournamentId);  // 3-4 queries per user
}
```

**Impact:** For 100 users = 300-400 database queries

**Optimized:**
```php
public function recalculateUserScores(int $tournamentId): void
{
    // Single query with aggregation
    $scores = DB::table('game_tipps')
        ->join('games', 'game_tipps.game_id', '=', 'games.id')
        ->where('games.tournament_id', $tournamentId)
        ->where('games.is_finished', true)
        ->groupBy('game_tipps.user_id')
        ->select(
            'game_tipps.user_id',
            DB::raw('SUM(game_tipps.score) as game_points'),
            DB::raw('COUNT(*) as tipp_count')
        )
        ->get();

    // Batch update
    foreach ($scores as $score) {
        UserScore::updateOrCreate(
            ['user_id' => $score->user_id, 'tournament_id' => $tournamentId],
            ['game_points' => $score->game_points, 'tipp_count' => $score->tipp_count]
        );
    }
}
```

### 2. Missing Database Indexes

**Create migration:**
```bash
php artisan make:migration add_performance_indexes
```

```php
public function up(): void
{
    // game_tipps table
    Schema::table('game_tipps', function (Blueprint $table) {
        $table->index('user_id');
        $table->index('score');
        $table->index(['user_id', 'game_id']);
    });

    // special_tipps table
    Schema::table('special_tipps', function (Blueprint $table) {
        $table->index('user_id');
        $table->index('special_tipp_spec_id');
        $table->index(['user_id', 'special_tipp_spec_id']);
    });

    // games table (if not exists)
    Schema::table('games', function (Blueprint $table) {
        $table->index(['is_finished', 'tournament_id']);
    });

    // user_scores table
    Schema::table('user_scores', function (Blueprint $table) {
        $table->index(['tournament_id', 'rank']);
        $table->index(['tournament_id', 'total_points']);
    });
}
```

### 3. Cache Configuration

**Current:** Database cache (bottleneck under load)

**Update `.env` for production:**
```env
CACHE_STORE=redis
# or for simpler setup:
CACHE_STORE=file

QUEUE_CONNECTION=redis
# or:
QUEUE_CONNECTION=database

SESSION_DRIVER=redis
# or:
SESSION_DRIVER=cookie
```

---

## High Priority Optimizations

### 4. Cache Active Tournament

**Problem:** `Tournament::where('is_active', true)->first()` called in 8+ components

**Solution - Create helper:**
```php
// app/Helpers/TournamentHelper.php
namespace App\Helpers;

use App\Models\Tournament;
use Illuminate\Support\Facades\Cache;

class TournamentHelper
{
    public static function active(): ?Tournament
    {
        return Cache::remember('active_tournament', 3600, function () {
            return Tournament::where('is_active', true)->first();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('active_tournament');
    }
}
```

**Usage in Livewire:**
```php
use App\Helpers\TournamentHelper;

#[Computed]
public function tournament()
{
    return TournamentHelper::active();
}
```

### 5. Cache User Rankings

**File:** `ranking.blade.php`

```php
#[Computed]
public function rankings()
{
    $cacheKey = "rankings:{$this->tournament->id}:{$this->selectedTippGroupId}";

    return Cache::remember($cacheKey, 300, function () {  // 5 minutes
        $query = UserScore::where('tournament_id', $this->tournament->id)
            ->with(['user', 'championTeam.nation']);

        if ($this->selectedTippGroupId) {
            // ... filter logic
        }

        return $query->orderBy('rank')->get();
    });
}
```

**Clear cache when scores update:**
```php
// In ScoreCalculationService after updating rankings
Cache::tags(['rankings'])->flush();
// or specific key
Cache::forget("rankings:{$tournamentId}:*");
```

### 6. Queue Score Calculations

**Create job:**
```bash
php artisan make:job CalculateGameScores
```

```php
// app/Jobs/CalculateGameScores.php
namespace App\Jobs;

use App\Models\Game;
use App\Services\ScoreCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateGameScores implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Game $game
    ) {}

    public function handle(ScoreCalculationService $service): void
    {
        $service->calculateGameScores($this->game);
    }
}
```

**Usage in admin controller:**
```php
// Instead of synchronous call
// $scoreService->calculateGameScores($game);

// Dispatch to queue
CalculateGameScores::dispatch($game);
```

### 7. Optimize Dashboard Queries

**Current (4 separate queries):**
```php
$totalTipps = GameTipp::whereHas('game', ...)->count();
$totalPoints = GameTipp::whereHas('game', ...)->sum('score');
$perfectTipps = GameTipp::whereHas('game', ...)->count();
```

**Optimized (1 query):**
```php
#[Computed]
public function userStats()
{
    if (!$this->tournament) return null;

    return DB::table('game_tipps')
        ->join('games', 'game_tipps.game_id', '=', 'games.id')
        ->where('game_tipps.user_id', auth()->id())
        ->where('games.tournament_id', $this->tournament->id)
        ->select(
            DB::raw('COUNT(*) as total_tipps'),
            DB::raw('SUM(CASE WHEN games.is_finished = 1 THEN game_tipps.score ELSE 0 END) as total_points'),
            DB::raw('SUM(CASE WHEN games.is_finished = 1 AND game_tipps.is_goals_home_correct = 1 AND game_tipps.is_goals_visitor_correct = 1 THEN 1 ELSE 0 END) as perfect_tipps')
        )
        ->first();
}
```

---

## Medium Priority Optimizations

### 8. Eager Loading in Livewire Components

**Games page - already good:**
```php
Game::with(['homeTeam.nation', 'visitorTeam.nation', 'penaltyWinnerTeam.nation', 'location', 'tipps'])
```

**Stats page - needs improvement:**
```php
// Current: User::find() in loop
// Better: Load all users upfront
$users = User::whereIn('id', $userIds)->get()->keyBy('id');
```

### 9. Livewire Component Caching

**For static data like knockout stages:**
```php
#[Computed(persist: true)]  // Livewire 3 feature
public function knockoutStages()
{
    return [
        'ROUND_OF_16' => 'Achtelfinale',
        'QUARTER_FINAL' => 'Viertelfinale',
        // ...
    ];
}
```

### 10. Pagination for Large Lists

**If user base grows significantly:**
```php
// Instead of
return $query->get();

// Use pagination
return $query->paginate(50);
```

---

## Infrastructure Recommendations

### For 100-500 Users

```
Web Server: Single VPS (2 CPU, 4GB RAM)
Database: SQLite or MySQL on same server
Cache: File-based
Queue: Database driver (sync for dev)
```

### For 500-2000 Users

```
Web Server: VPS (4 CPU, 8GB RAM)
Database: MySQL/PostgreSQL (separate or same server)
Cache: Redis
Queue: Redis with Horizon
Sessions: Redis
```

### For 2000+ Users

```
Web Server: Load balanced (2+ servers)
Database: Managed MySQL/PostgreSQL with read replicas
Cache: Redis Cluster
Queue: Redis with Horizon (dedicated worker)
CDN: For static assets
```

---

## Monitoring & Profiling

### 1. Laravel Telescope (Development)

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

### 2. Query Logging

```php
// In AppServiceProvider for development
if (config('app.debug')) {
    DB::listen(function ($query) {
        if ($query->time > 100) {  // Log slow queries (>100ms)
            Log::warning('Slow query', [
                'sql' => $query->sql,
                'time' => $query->time,
            ]);
        }
    });
}
```

### 3. Key Metrics to Monitor

- Response time per page (target: <200ms)
- Database queries per request (target: <20)
- Memory usage per request (target: <64MB)
- Queue job processing time
- Cache hit rate (target: >80%)

---

## Quick Wins Checklist

```
[ ] Add database indexes (migration)
[ ] Change CACHE_STORE to file or redis
[ ] Cache active tournament
[ ] Optimize dashboard to single query
[ ] Add eager loading where missing
[ ] Queue score calculations
[ ] Cache rankings (5 min TTL)
```

---

## Performance Testing

Before going live, run load tests:

```bash
# Install k6 or Apache Bench
# Test with expected concurrent users

# Example with curl timing
time curl -s https://your-app.com/ranking > /dev/null

# Target: < 500ms for all pages under load
```
