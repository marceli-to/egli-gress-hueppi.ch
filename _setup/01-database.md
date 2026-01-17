# Database Schema & Migrations

## Entity Relationship Overview

```
Tournament (1) ─── (*) Team ─── (1) Nation
     │                │
     │                └─── (*) Tag
     │
     └─── (*) Game ─── (1) Location
              │
              └─── (*) GameTipp ─── (1) User

User ─── (*) SpecialTipp ─── (1) SpecialTippSpec
  │
  ├─── (*) UserScore
  ├─── (*) UserScoreHistory
  └─── (*) TippGroup (via GroupMember)
```

## Core Tables

### tournaments
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| name | varchar(100) | e.g., "WM 2022" |
| is_active | boolean | Only one active at a time |
| created_at | timestamp | |
| updated_at | timestamp | |

### nations
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| name | varchar(100) | e.g., "Switzerland" |
| code | varchar(3) | ISO code, e.g., "SUI" |
| fifa_ranking | int | Current FIFA ranking |
| champion_count | int | Number of World Cup wins |
| created_at | timestamp | |
| updated_at | timestamp | |

### teams
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| tournament_id | bigint | FK to tournaments |
| nation_id | bigint | FK to nations |
| group_name | char(1) | A-H |
| points | int | Group stage points |
| goals_for | int | Goals scored |
| goals_against | int | Goals conceded |
| wins | int | |
| draws | int | |
| losses | int | |
| fair_play_points | int | For tiebreakers |
| created_at | timestamp | |
| updated_at | timestamp | |

### locations
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| name | varchar(100) | Stadium name |
| city | varchar(100) | |
| country | varchar(100) | |
| created_at | timestamp | |
| updated_at | timestamp | |

### games
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| tournament_id | bigint | FK to tournaments |
| game_type | enum | 'GROUP', 'ROUND_OF_16', 'QUARTER_FINAL', 'SEMI_FINAL', 'THIRD_PLACE', 'FINAL' |
| group_name | char(1) | NULL for knockout games |
| kickoff_at | timestamp | Game start time (UTC) |
| location_id | bigint | FK to locations |
| home_team_id | bigint | FK to teams (nullable for knockout) |
| visitor_team_id | bigint | FK to teams (nullable for knockout) |
| home_team_placeholder | varchar(50) | e.g., "Winner Group A" |
| visitor_team_placeholder | varchar(50) | e.g., "Runner-up Group B" |
| goals_home | int | NULL until played |
| goals_visitor | int | NULL until played |
| goals_home_halftime | int | |
| goals_visitor_halftime | int | |
| is_finished | boolean | Game completed |
| has_penalty_shootout | boolean | |
| penalty_winner_team_id | bigint | Winner of shootout |
| created_at | timestamp | |
| updated_at | timestamp | |

### game_tipps
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| user_id | bigint | FK to users |
| game_id | bigint | FK to games |
| goals_home | int | Predicted home goals |
| goals_visitor | int | Predicted visitor goals |
| penalty_winner_team_id | bigint | For knockout games |
| score | int | Calculated points (0-4) |
| is_tendency_correct | boolean | Correct winner/draw |
| is_difference_correct | boolean | Correct goal difference |
| is_goals_home_correct | boolean | Exact home goals |
| is_goals_visitor_correct | boolean | Exact visitor goals |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint:** (user_id, game_id)

### special_tipp_specs
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| tournament_id | bigint | FK to tournaments |
| name | varchar(100) | e.g., "Weltmeister" |
| type | enum | 'WINNER', 'FINAL_RANKING', 'TOTAL_GOALS' |
| value | int | Points awarded if correct |
| team_id | bigint | FK to teams (for correct answer) |
| created_at | timestamp | |
| updated_at | timestamp | |

### special_tipps
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| user_id | bigint | FK to users |
| special_tipp_spec_id | bigint | FK to special_tipp_specs |
| predicted_team_id | bigint | FK to teams (for winner/ranking) |
| predicted_value | int | For total goals prediction |
| score | int | Calculated points |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint:** (user_id, special_tipp_spec_id)

### tipp_groups
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| tournament_id | bigint | FK to tournaments |
| name | varchar(100) | Group name |
| password | varchar(255) | Hashed, nullable (public groups) |
| owner_user_id | bigint | FK to users (creator) |
| created_at | timestamp | |
| updated_at | timestamp | |

### group_members (pivot)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| tipp_group_id | bigint | FK to tipp_groups |
| user_id | bigint | FK to users |
| rank | int | Current rank in group |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint:** (tipp_group_id, user_id)

### user_scores
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| user_id | bigint | FK to users |
| tournament_id | bigint | FK to tournaments |
| total_points | int | Game + special points |
| game_points | int | Points from game tipps |
| special_points | int | Points from special tipps |
| rank | int | Global ranking position |
| rank_delta | int | Change since last update |
| tipp_count | int | Number of predictions made |
| average_score | decimal(4,2) | Points per prediction |
| champion_team_id | bigint | User's champion prediction |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint:** (user_id, tournament_id)

### user_score_histories
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| user_id | bigint | FK to users |
| tournament_id | bigint | FK to tournaments |
| game_day | int | Match day number |
| points | int | Total points at this day |
| rank | int | Rank at this day |
| rank_delta | int | Change from previous day |
| created_at | timestamp | |
| updated_at | timestamp | |

## Laravel Migration Examples

### Create tournaments table

```php
Schema::create('tournaments', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->boolean('is_active')->default(false);
    $table->timestamps();

    $table->index('is_active');
});
```

### Create games table

```php
Schema::create('games', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
    $table->enum('game_type', [
        'GROUP', 'ROUND_OF_16', 'QUARTER_FINAL',
        'SEMI_FINAL', 'THIRD_PLACE', 'FINAL'
    ]);
    $table->char('group_name', 1)->nullable();
    $table->timestamp('kickoff_at');
    $table->foreignId('location_id')->constrained();
    $table->foreignId('home_team_id')->nullable()->constrained('teams');
    $table->foreignId('visitor_team_id')->nullable()->constrained('teams');
    $table->string('home_team_placeholder', 50)->nullable();
    $table->string('visitor_team_placeholder', 50)->nullable();
    $table->unsignedTinyInteger('goals_home')->nullable();
    $table->unsignedTinyInteger('goals_visitor')->nullable();
    $table->unsignedTinyInteger('goals_home_halftime')->nullable();
    $table->unsignedTinyInteger('goals_visitor_halftime')->nullable();
    $table->boolean('is_finished')->default(false);
    $table->boolean('has_penalty_shootout')->default(false);
    $table->foreignId('penalty_winner_team_id')->nullable()->constrained('teams');
    $table->timestamps();

    $table->index(['tournament_id', 'game_type']);
    $table->index('kickoff_at');
});
```

### Create game_tipps table

```php
Schema::create('game_tipps', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('game_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('goals_home');
    $table->unsignedTinyInteger('goals_visitor');
    $table->foreignId('penalty_winner_team_id')->nullable()->constrained('teams');
    $table->unsignedTinyInteger('score')->default(0);
    $table->boolean('is_tendency_correct')->default(false);
    $table->boolean('is_difference_correct')->default(false);
    $table->boolean('is_goals_home_correct')->default(false);
    $table->boolean('is_goals_visitor_correct')->default(false);
    $table->timestamps();

    $table->unique(['user_id', 'game_id']);
    $table->index('game_id');
});
```

## Indexes to Consider

```php
// Performance indexes
$table->index(['tournament_id', 'is_active']); // tournaments
$table->index(['tournament_id', 'group_name']); // teams
$table->index(['user_id', 'tournament_id']); // user_scores
$table->index(['tournament_id', 'rank']); // user_scores (for ranking queries)
```

## Seeder Structure

```php
// DatabaseSeeder.php
$this->call([
    NationSeeder::class,      // All FIFA nations
    LocationSeeder::class,    // Tournament venues
    TournamentSeeder::class,  // Tournament setup
    TeamSeeder::class,        // Teams in tournament
    GameSeeder::class,        // All scheduled games
    SpecialTippSpecSeeder::class, // Special bet definitions
]);
```

## Data Migration from Original

The original data is in `/tippspiel-flyway/db/migrations/V1.1__loadInitialData_2022.sql`. This contains:
- All 2022 World Cup teams
- All group stage games
- All knockout stage games
- Locations (stadiums)

This can be converted to Laravel seeders or imported directly.
