# World Cup Prediction Game - Laravel/Livewire Port

## Original Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Java 18, Jakarta EE 9.0, OpenLiberty |
| ORM | JPA/EclipseLink |
| Database | PostgreSQL |
| Migrations | Flyway |
| Auth | Keycloak (OAuth2/JWT) |
| Frontend | Vue.js 3, Vite, Pinia |

## Target Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12 |
| ORM | Eloquent |
| Database | MySQL |
| Migrations | Laravel Migrations |
| Auth | Laravel Breeze (Livewire) + Sanctum (optional API) |
| Frontend | Livewire 3, Alpine.js, Tailwind CSS 4 |

## Project Structure (Proposed)

```
tippspiel-laravel/
├── app/
│   ├── Models/
│   │   ├── Tournament.php
│   │   ├── Team.php
│   │   ├── Nation.php
│   │   ├── Game.php
│   │   ├── Location.php
│   │   ├── GameTipp.php
│   │   ├── SpecialTipp.php
│   │   ├── TippGroup.php
│   │   ├── UserScore.php
│   │   └── UserScoreHistory.php
│   ├── Livewire/
│   │   ├── Games/
│   │   ├── Tipps/
│   │   ├── Ranking/
│   │   └── Admin/
│   ├── Services/
│   │   ├── ScoringService.php
│   │   ├── RankingService.php
│   │   └── TournamentService.php
│   └── Enums/
│       ├── GameType.php
│       └── SpecialTippType.php
├── database/
│   ├── migrations/
│   └── seeders/
└── resources/
    └── views/
        └── livewire/
```

## Core Features to Implement

1. **User Management**
   - Registration/Login
   - Profile management
   - Role-based access (admin, user)

2. **Tournament Management**
   - Create/manage tournaments
   - Add teams and groups
   - Schedule games

3. **Game Predictions (Tipps)**
   - Submit predictions before kickoff
   - View own and others' predictions (after game)
   - Special predictions (champion, goals, etc.)

4. **Scoring System**
   - Automatic score calculation
   - Exact score: 4 points
   - Goal difference: 3 points
   - Correct tendency: 1 point

5. **Rankings**
   - Global ranking
   - Group/league rankings
   - Historical progression

6. **Admin Functions**
   - Enter game results
   - Manage tournaments
   - View all users

## Migration Strategy

### Phase 1: Database Setup
- Create Laravel migrations from existing schema
- Set up seeders for initial data
- See: `01-database.md`

### Phase 2: Backend Logic
- Create Eloquent models with relationships
- Implement scoring service
- Build Livewire components
- See: `02-backend-api.md`

### Phase 3: Frontend
- Convert Vue components to Livewire
- Implement Alpine.js interactions
- Style with Tailwind CSS
- See: `03-frontend.md`

### Phase 4: Authentication
- Set up Laravel auth
- Implement roles/permissions
- See: `04-authentication.md`

### Phase 5: Business Logic
- Implement scoring algorithms
- Group ranking calculations
- See: `05-game-logic.md`

## Key Considerations

### Timezone Handling
- Store all times in UTC
- Display in user's timezone
- Critical for kickoff time validation

### Caching Strategy
- Cache rankings (invalidate on score update)
- Cache team/game data
- Use Laravel's cache system

### Real-time Updates
- Consider Laravel Echo for live updates
- Livewire polling as alternative

### Data Migration
- Export existing data from PostgreSQL
- Transform to new schema format
- Import via seeders or direct migration
