# Security Audit

## Executive Summary

This document outlines security findings and recommendations for the Tippspiel application. The application uses Laravel 11 with Livewire and implements most security best practices, but has gaps in authorization, rate limiting, and configuration that should be addressed before production.

---

## Critical Issues (Fix Before Production)

### 1. Environment Configuration

| Issue | File | Action |
|-------|------|--------|
| Debug mode | `.env` | Set `APP_DEBUG=false` |
| App key | `.env` | Run `php artisan key:generate` |
| Session cookie | `.env` | Set `SESSION_SECURE_COOKIE=true` |

### 2. Mass Assignment - User Model

**File:** `app/Models/User.php`

```php
// CURRENT - Risk: is_admin in fillable
protected $fillable = [
    'name', 'display_name', 'email', 'password',
    'is_admin',      // REMOVE
    'is_simulated',  // REMOVE
];

// BETTER - Use guarded
protected $guarded = ['id', 'is_admin', 'is_simulated'];
```

### 3. Mass Assignment - Score Fields

**File:** `app/Models/GameTipp.php`

Remove calculated fields from fillable:
```php
protected $fillable = [
    'user_id',
    'game_id',
    'goals_home',
    'goals_visitor',
    'penalty_winner_team_id',
    // REMOVE these - should only be set by ScoreCalculationService:
    // 'score',
    // 'is_tendency_correct',
    // 'is_difference_correct',
    // 'is_goals_home_correct',
    // 'is_goals_visitor_correct',
];
```

---

## High Priority Issues

### 4. Rate Limiting Missing

**Affected Operations:**
- Group password attempts (`groups/index.blade.php`)
- Game predictions (`games/index.blade.php`)
- Special predictions (`special-tipps.blade.php`)

**Solution - Add to Livewire components:**

```php
use Illuminate\Support\Facades\RateLimiter;

public function joinPrivateGroup(): void
{
    $key = 'group-join:' . auth()->id();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        $this->addError('joinGroupPassword', 'Zu viele Versuche. Bitte warten.');
        return;
    }

    RateLimiter::hit($key, 60); // 5 attempts per minute

    // ... existing logic
}
```

### 5. Authorization Checks in Admin

**File:** `resources/views/livewire/pages/admin/games.blade.php`

```php
// CURRENT
public function saveResult(): void
{
    $game = Game::find($this->editingGameId);
    // ...
}

// BETTER - Add explicit authorization
public function saveResult(): void
{
    $game = Game::findOrFail($this->editingGameId);
    $this->authorize('update', $game); // Add policy check
    // ...
}
```

### 6. Database Transactions for Score Calculation

**File:** `app/Services/ScoreCalculationService.php`

```php
use Illuminate\Support\Facades\DB;

public function calculateGameScores(Game $game): void
{
    DB::transaction(function () use ($game) {
        // All score calculations inside transaction
        $tipps = GameTipp::where('game_id', $game->id)->get();

        foreach ($tipps as $tipp) {
            $this->calculateTippScore($tipp, $game);
        }

        $this->recalculateUserScores($game->tournament_id);
        $this->trackScoreHistory($game);
    });
}
```

---

## Medium Priority Issues

### 7. IDOR Vulnerabilities

**Ranking Comparison** (`ranking.blade.php:118-124`)
- Users can view any other user's predictions
- Decision needed: Is this intentional for a public ranking?

**Group Operations** (`groups/index.blade.php:92-154`)
- Verify group membership before operations

```php
public function leaveGroup(int $groupId): void
{
    $membership = GroupMember::where('tipp_group_id', $groupId)
        ->where('user_id', auth()->id())
        ->first();

    if (!$membership) {
        return; // Not a member
    }

    // Check if owner
    $group = TippGroup::find($groupId);
    if ($group->owner_user_id === auth()->id()) {
        return; // Owners can't leave
    }

    $membership->delete();
}
```

### 8. Race Conditions in Game Updates

**File:** `admin/games.blade.php`

```php
// Use pessimistic locking
public function saveResult(): void
{
    DB::transaction(function () {
        $game = Game::lockForUpdate()->find($this->editingGameId);

        if (!$game) {
            $this->cancelEdit();
            return;
        }

        // ... update logic
    });
}
```

### 9. Input Validation Improvements

**Cross-field validation for halftime scores:**

```php
$this->validate([
    'goalsHome' => 'required|integer|min:0|max:20',
    'goalsVisitor' => 'required|integer|min:0|max:20',
    'goalsHomeHalftime' => 'nullable|integer|min:0|lte:goalsHome',
    'goalsVisitorHalftime' => 'nullable|integer|min:0|lte:goalsVisitor',
]);
```

---

## What's Already Secure

| Feature | Status | Location |
|---------|--------|----------|
| CSRF Protection | Livewire handles automatically | All forms |
| SQL Injection | Eloquent ORM used throughout | All queries |
| Password Hashing | bcrypt with 12 rounds | `config/hashing.php` |
| XSS Prevention | Blade auto-escaping `{{ }}` | All templates |
| Session Security | HTTPOnly + SameSite=lax | `config/session.php` |
| Login Rate Limiting | 5 attempts/minute | `LoginForm.php` |
| Email Verification | Signed URLs + throttle | `routes/auth.php` |
| Admin Middleware | Proper 403 response | `AdminMiddleware.php` |
| Hidden Attributes | Passwords hidden from JSON | User, TippGroup models |

---

## Security Checklist for Production

```
[ ] APP_DEBUG=false
[ ] APP_KEY generated
[ ] SESSION_SECURE_COOKIE=true
[ ] HTTPS enforced (web server config)
[ ] Database credentials secured
[ ] Rate limiting on sensitive operations
[ ] User model guarded fields
[ ] Score fields removed from fillable
[ ] Database transactions in ScoreCalculationService
[ ] Security headers configured (CSP, X-Frame-Options)
```

---

## Recommended Security Headers

Add to `app/Http/Middleware` or web server config:

```php
// In a new middleware or AppServiceProvider
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('X-Frame-Options', 'SAMEORIGIN');
$response->headers->set('X-XSS-Protection', '1; mode=block');
$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
```

---

## Regular Security Maintenance

1. **Weekly:** Check `composer audit` for vulnerabilities
2. **Monthly:** Review Laravel security advisories
3. **Quarterly:** Full dependency update
4. **Annually:** Security penetration test
