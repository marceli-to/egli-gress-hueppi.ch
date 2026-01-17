# Backend & API Structure

## Original API Endpoints

The original Java application exposes REST endpoints. With Livewire, many of these become unnecessary as Livewire handles data loading directly. However, some may still be needed for AJAX operations.

### Public Endpoints (No Auth)

| Original | Purpose | Livewire Approach |
|----------|---------|-------------------|
| GET `/public/game` | Get all games | Direct Eloquent in component |
| GET `/public/team` | Get all teams | Direct Eloquent in component |
| GET `/public/ranking` | Get rankings | Direct Eloquent in component |
| GET `/public/results` | Get results | Direct Eloquent in component |

### Private Endpoints (Auth Required)

| Original | Purpose | Livewire Approach |
|----------|---------|-------------------|
| GET `/private/tipp/group` | User's group tipps | Livewire component property |
| GET `/private/tipp/finals` | User's final tipps | Livewire component property |
| PUT `/private/tipp/GROUP/{id}` | Save group tipp | Livewire action method |
| PUT `/private/tipp/FINAL/{id}` | Save final tipp | Livewire action method |
| GET `/private/ranking` | Personal ranking | Livewire component property |

### Admin Endpoints

| Original | Purpose | Livewire Approach |
|----------|---------|-------------------|
| PUT `/private/game/GROUP/{id}` | Save group result | Livewire admin component |
| PUT `/private/game/FINAL/{id}` | Save final result | Livewire admin component |

## Eloquent Models

### Tournament.php

```php
class Tournament extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function tippGroups(): HasMany
    {
        return $this->hasMany(TippGroup::class);
    }

    public function userScores(): HasMany
    {
        return $this->hasMany(UserScore::class);
    }

    public static function active(): ?Tournament
    {
        return static::where('is_active', true)->first();
    }
}
```

### Team.php

```php
class Team extends Model
{
    protected $fillable = [
        'tournament_id', 'nation_id', 'group_name',
        'points', 'goals_for', 'goals_against',
        'wins', 'draws', 'losses', 'fair_play_points'
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function nation(): BelongsTo
    {
        return $this->belongsTo(Nation::class);
    }

    public function homeGames(): HasMany
    {
        return $this->hasMany(Game::class, 'home_team_id');
    }

    public function visitorGames(): HasMany
    {
        return $this->hasMany(Game::class, 'visitor_team_id');
    }

    public function getGoalDifferenceAttribute(): int
    {
        return $this->goals_for - $this->goals_against;
    }
}
```

### Game.php

```php
class Game extends Model
{
    protected $fillable = [
        'tournament_id', 'game_type', 'group_name', 'kickoff_at',
        'location_id', 'home_team_id', 'visitor_team_id',
        'home_team_placeholder', 'visitor_team_placeholder',
        'goals_home', 'goals_visitor', 'goals_home_halftime',
        'goals_visitor_halftime', 'is_finished',
        'has_penalty_shootout', 'penalty_winner_team_id'
    ];

    protected $casts = [
        'kickoff_at' => 'datetime',
        'is_finished' => 'boolean',
        'has_penalty_shootout' => 'boolean',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function visitorTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'visitor_team_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function tipps(): HasMany
    {
        return $this->hasMany(GameTipp::class);
    }

    public function userTipp(): HasOne
    {
        return $this->hasOne(GameTipp::class)
            ->where('user_id', auth()->id());
    }

    public function canBeTipped(): bool
    {
        return $this->kickoff_at->isFuture();
    }

    public function isGroupGame(): bool
    {
        return $this->game_type === 'GROUP';
    }

    public function isKnockout(): bool
    {
        return !$this->isGroupGame();
    }
}
```

### GameTipp.php

```php
class GameTipp extends Model
{
    protected $fillable = [
        'user_id', 'game_id', 'goals_home', 'goals_visitor',
        'penalty_winner_team_id', 'score',
        'is_tendency_correct', 'is_difference_correct',
        'is_goals_home_correct', 'is_goals_visitor_correct'
    ];

    protected $casts = [
        'is_tendency_correct' => 'boolean',
        'is_difference_correct' => 'boolean',
        'is_goals_home_correct' => 'boolean',
        'is_goals_visitor_correct' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function penaltyWinner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'penalty_winner_team_id');
    }
}
```

### UserScore.php

```php
class UserScore extends Model
{
    protected $fillable = [
        'user_id', 'tournament_id', 'total_points',
        'game_points', 'special_points', 'rank',
        'rank_delta', 'tipp_count', 'average_score',
        'champion_team_id'
    ];

    protected $casts = [
        'average_score' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function championTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'champion_team_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(UserScoreHistory::class, 'user_id', 'user_id')
            ->where('tournament_id', $this->tournament_id);
    }
}
```

## Services

### ScoringService.php

```php
class ScoringService
{
    public function calculateTippScore(GameTipp $tipp, Game $game): int
    {
        if (!$game->is_finished) {
            return 0;
        }

        $score = 0;
        $actualHome = $game->goals_home;
        $actualVisitor = $game->goals_visitor;
        $predictedHome = $tipp->goals_home;
        $predictedVisitor = $tipp->goals_visitor;

        // Check tendency (1 point)
        $actualTendency = $this->getTendency($actualHome, $actualVisitor);
        $predictedTendency = $this->getTendency($predictedHome, $predictedVisitor);

        if ($actualTendency === $predictedTendency) {
            $score += 1;
            $tipp->is_tendency_correct = true;

            // Check goal difference (additional 2 points)
            $actualDiff = $actualHome - $actualVisitor;
            $predictedDiff = $predictedHome - $predictedVisitor;

            if ($actualDiff === $predictedDiff) {
                $score += 2;
                $tipp->is_difference_correct = true;

                // Check exact score (additional 1 point)
                if ($actualHome === $predictedHome && $actualVisitor === $predictedVisitor) {
                    $score += 1;
                    $tipp->is_goals_home_correct = true;
                    $tipp->is_goals_visitor_correct = true;
                }
            }
        }

        return $score;
    }

    private function getTendency(int $home, int $visitor): int
    {
        if ($home > $visitor) return 1;  // Home win
        if ($home < $visitor) return -1; // Away win
        return 0; // Draw
    }

    public function recalculateAllScores(Game $game): void
    {
        $tipps = $game->tipps()->get();

        foreach ($tipps as $tipp) {
            $tipp->score = $this->calculateTippScore($tipp, $game);
            $tipp->save();
        }

        // Update user scores
        $this->updateUserScores($game->tournament);
    }

    public function updateUserScores(Tournament $tournament): void
    {
        // Implementation in 05-game-logic.md
    }
}
```

### RankingService.php

```php
class RankingService
{
    public function getGlobalRanking(Tournament $tournament): Collection
    {
        return UserScore::where('tournament_id', $tournament->id)
            ->with('user', 'championTeam.nation')
            ->orderByDesc('total_points')
            ->orderByDesc('average_score')
            ->get();
    }

    public function getGroupRanking(TippGroup $group): Collection
    {
        return $group->members()
            ->with(['userScore' => fn($q) => $q->where('tournament_id', $group->tournament_id)])
            ->get()
            ->sortByDesc(fn($member) => $member->userScore?->total_points ?? 0);
    }

    public function updateRanks(Tournament $tournament): void
    {
        $scores = UserScore::where('tournament_id', $tournament->id)
            ->orderByDesc('total_points')
            ->orderByDesc('average_score')
            ->get();

        $rank = 0;
        $previousPoints = null;

        foreach ($scores as $score) {
            if ($score->total_points !== $previousPoints) {
                $rank++;
            }

            $oldRank = $score->rank;
            $score->rank = $rank;
            $score->rank_delta = $oldRank ? ($oldRank - $rank) : 0;
            $score->save();

            $previousPoints = $score->total_points;
        }
    }
}
```

## Livewire Components Structure

### Games List

```php
// app/Livewire/Games/GamesList.php
class GamesList extends Component
{
    public string $filter = 'all'; // all, group, knockout

    public function render()
    {
        $games = Game::query()
            ->whereHas('tournament', fn($q) => $q->where('is_active', true))
            ->with(['homeTeam.nation', 'visitorTeam.nation', 'location', 'userTipp'])
            ->when($this->filter === 'group', fn($q) => $q->where('game_type', 'GROUP'))
            ->when($this->filter === 'knockout', fn($q) => $q->where('game_type', '!=', 'GROUP'))
            ->orderBy('kickoff_at')
            ->get()
            ->groupBy(fn($game) => $game->kickoff_at->format('Y-m-d'));

        return view('livewire.games.games-list', [
            'gamesByDate' => $games,
        ]);
    }
}
```

### Tipp Form

```php
// app/Livewire/Tipps/TippForm.php
class TippForm extends Component
{
    public Game $game;
    public ?int $goalsHome = null;
    public ?int $goalsVisitor = null;
    public ?int $penaltyWinner = null;

    protected $rules = [
        'goalsHome' => 'required|integer|min:0|max:20',
        'goalsVisitor' => 'required|integer|min:0|max:20',
        'penaltyWinner' => 'nullable|exists:teams,id',
    ];

    public function mount(Game $game)
    {
        $this->game = $game;

        if ($tipp = $game->userTipp) {
            $this->goalsHome = $tipp->goals_home;
            $this->goalsVisitor = $tipp->goals_visitor;
            $this->penaltyWinner = $tipp->penalty_winner_team_id;
        }
    }

    public function save()
    {
        if (!$this->game->canBeTipped()) {
            $this->addError('game', 'Game has already started');
            return;
        }

        $this->validate();

        GameTipp::updateOrCreate(
            ['user_id' => auth()->id(), 'game_id' => $this->game->id],
            [
                'goals_home' => $this->goalsHome,
                'goals_visitor' => $this->goalsVisitor,
                'penalty_winner_team_id' => $this->penaltyWinner,
            ]
        );

        $this->dispatch('tipp-saved');
    }

    public function render()
    {
        return view('livewire.tipps.tipp-form');
    }
}
```

### Ranking Table

```php
// app/Livewire/Ranking/RankingTable.php
class RankingTable extends Component
{
    public ?int $groupId = null;

    public function render()
    {
        $tournament = Tournament::active();
        $rankingService = app(RankingService::class);

        if ($this->groupId) {
            $group = TippGroup::findOrFail($this->groupId);
            $ranking = $rankingService->getGroupRanking($group);
        } else {
            $ranking = $rankingService->getGlobalRanking($tournament);
        }

        return view('livewire.ranking.ranking-table', [
            'ranking' => $ranking,
        ]);
    }
}
```

## Routes

```php
// routes/web.php
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware(['auth'])->group(function () {
    // Games & Tipps
    Route::get('/games', Games\GamesList::class)->name('games');
    Route::get('/games/{game}', Games\GameDetail::class)->name('games.show');

    // Ranking
    Route::get('/ranking', Ranking\RankingTable::class)->name('ranking');

    // Profile
    Route::get('/profile', Profile\UserProfile::class)->name('profile');

    // Groups
    Route::get('/groups', Groups\GroupsList::class)->name('groups');
    Route::get('/groups/{group}', Groups\GroupDetail::class)->name('groups.show');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/games', Admin\ManageGames::class)->name('admin.games');
    Route::get('/tournament', Admin\ManageTournament::class)->name('admin.tournament');
});
```

## Middleware

### EnsureAdmin.php

```php
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->is_admin) {
            abort(403);
        }

        return $next($request);
    }
}
```

## API Routes (if needed)

For any AJAX operations not handled by Livewire:

```php
// routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/games', [GameController::class, 'index']);
    Route::get('/ranking', [RankingController::class, 'index']);
});
```
