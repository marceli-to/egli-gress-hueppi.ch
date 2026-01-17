# Authentication System

## Original System: Keycloak

The original application uses Keycloak for OAuth2/OpenID Connect authentication:

- Realm-based multi-tenancy
- JWT tokens for API authentication
- Role-based access control (ts-admin, ts-user)
- PKCE flow for frontend
- Token refresh handling

## Laravel 12 Solution: Breeze + Sanctum

For Laravel 12, we use a combination of:

| Package | Purpose |
|---------|---------|
| **Laravel Breeze** | Web authentication (login, register, password reset, email verification) |
| **Laravel Sanctum** | API token authentication (optional, for mobile apps or external API access) |

### What Each Package Provides

**Breeze (required):**
- Login / Register pages (Livewire components)
- Password reset flow
- Email verification
- Profile management
- Tailwind CSS styling
- Session-based authentication for web

**Sanctum (optional):**
- API token generation
- Token abilities/scopes
- Token expiration
- Perfect for mobile apps or third-party integrations

## Installation

### Step 1: Create Laravel 12 Project

```bash
composer create-project laravel/laravel tippspiel "^12.0"
cd tippspiel
```

### Step 2: Install Breeze with Livewire

```bash
composer require laravel/breeze --dev
php artisan breeze:install livewire

npm install && npm run build
php artisan migrate
```

This creates:
```
app/Livewire/
├── Actions/Logout.php
├── Forms/LoginForm.php
├── Pages/Auth/
│   ├── ConfirmPassword.php
│   ├── ForgotPassword.php
│   ├── Login.php
│   ├── Register.php
│   ├── ResetPassword.php
│   └── VerifyEmail.php
resources/views/
├── livewire/pages/auth/
│   ├── confirm-password.blade.php
│   ├── forgot-password.blade.php
│   ├── login.blade.php
│   ├── register.blade.php
│   ├── reset-password.blade.php
│   └── verify-email.blade.php
├── layouts/
│   ├── app.blade.php
│   └── guest.blade.php
```

### Step 3: Install Sanctum (Optional - for API)

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

## User Model Setup

### Step 1: Add Custom Fields Migration

```bash
php artisan make:migration add_profile_fields_to_users_table
```

```php
// database/migrations/xxxx_add_profile_fields_to_users_table.php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->boolean('is_admin')->default(false);
        $table->string('display_name')->nullable();
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['is_admin', 'display_name']);
    });
}
```

### Step 2: Update User Model

```php
<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Add this for API tokens

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'display_name',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    // Relationships
    public function tipps(): HasMany
    {
        return $this->hasMany(GameTipp::class);
    }

    public function specialTipps(): HasMany
    {
        return $this->hasMany(SpecialTipp::class);
    }

    public function score(): HasOne
    {
        return $this->hasOne(UserScore::class)
            ->whereHas('tournament', fn($q) => $q->where('is_active', true));
    }

    public function scores(): HasMany
    {
        return $this->hasMany(UserScore::class);
    }

    public function tippGroups(): BelongsToMany
    {
        return $this->belongsToMany(TippGroup::class, 'group_members')
            ->withPivot('rank')
            ->withTimestamps();
    }

    public function ownedGroups(): HasMany
    {
        return $this->hasMany(TippGroup::class, 'owner_user_id');
    }

    // Accessors
    public function getDisplayNameAttribute(): string
    {
        return $this->attributes['display_name'] ?? $this->name;
    }

    // Helpers
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }
}
```

## Admin Middleware

### Create Middleware

```php
<?php
// app/Http/Middleware/EnsureUserIsAdmin.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
```

### Register Middleware

```php
// bootstrap/app.php
use App\Http\Middleware\EnsureUserIsAdmin;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

## Routes

### Web Routes

```php
<?php
// routes/web.php

use App\Livewire\Admin;
use App\Livewire\Games;
use App\Livewire\Groups;
use App\Livewire\Profile;
use App\Livewire\Ranking;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Public routes
Route::get('/ranking', Ranking\PublicRanking::class)->name('ranking');

// Authenticated routes (Breeze handles auth routes automatically)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/games', Games\GamesList::class)->name('games');
    Route::get('/my-tipps', Games\MyTipps::class)->name('my-tipps');
    Route::get('/special-tipps', Games\SpecialTipps::class)->name('special-tipps');
    Route::get('/groups', Groups\GroupsList::class)->name('groups');
    Route::get('/groups/{group}', Groups\GroupDetail::class)->name('groups.show');
    Route::get('/profile', Profile\UserProfile::class)->name('profile');
});

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', Admin\Dashboard::class)->name('dashboard');
    Route::get('/games', Admin\ManageGames::class)->name('games');
    Route::get('/results', Admin\EnterResults::class)->name('results');
    Route::get('/users', Admin\ManageUsers::class)->name('users');
    Route::get('/tournament', Admin\ManageTournament::class)->name('tournament');
});

require __DIR__.'/auth.php'; // Breeze auth routes
```

### API Routes (with Sanctum)

```php
<?php
// routes/api.php

use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\TippController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public API endpoints
Route::get('/games', [GameController::class, 'index']);
Route::get('/ranking', [RankingController::class, 'index']);

// Protected API endpoints (require Sanctum token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());
    Route::get('/my-tipps', [TippController::class, 'index']);
    Route::post('/tipps/{game}', [TippController::class, 'store']);
});
```

## Customizing Registration

### Add Display Name Field

```php
<?php
// app/Livewire/Pages/Auth/Register.php (modify Breeze default)

namespace App\Livewire\Pages\Auth;

use App\Models\Tournament;
use App\Models\User;
use App\Models\UserScore;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Register extends Component
{
    public string $name = '';
    public string $display_name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        // Create initial UserScore for active tournament
        if ($tournament = Tournament::where('is_active', true)->first()) {
            UserScore::create([
                'user_id' => $user->id,
                'tournament_id' => $tournament->id,
                'total_points' => 0,
                'game_points' => 0,
                'special_points' => 0,
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('games', absolute: false), navigate: true);
    }

    public function render()
    {
        return view('livewire.pages.auth.register');
    }
}
```

### Update Registration View

```blade
{{-- resources/views/livewire/pages/auth/register.blade.php --}}
<div>
    <form wire:submit="register">
        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Display Name -->
        <div class="mt-4">
            <x-input-label for="display_name" :value="__('Display Name (optional)')" />
            <x-text-input wire:model="display_name" id="display_name" class="block mt-1 w-full" type="text" name="display_name" autocomplete="nickname" />
            <x-input-error :messages="$errors->get('display_name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}" wire:navigate>
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</div>
```

## API Token Management (Sanctum)

If you need API access (e.g., for a mobile app), add token management to the profile:

### Token Management Component

```php
<?php
// app/Livewire/Profile/ApiTokens.php

namespace App\Livewire\Profile;

use Livewire\Component;

class ApiTokens extends Component
{
    public string $tokenName = '';
    public ?string $plainTextToken = null;

    public function createToken(): void
    {
        $this->validate([
            'tokenName' => ['required', 'string', 'max:255'],
        ]);

        $token = auth()->user()->createToken($this->tokenName);
        $this->plainTextToken = $token->plainTextToken;
        $this->tokenName = '';
    }

    public function deleteToken(int $tokenId): void
    {
        auth()->user()->tokens()->where('id', $tokenId)->delete();
    }

    public function render()
    {
        return view('livewire.profile.api-tokens', [
            'tokens' => auth()->user()->tokens,
        ]);
    }
}
```

### Token Management View

```blade
{{-- resources/views/livewire/profile/api-tokens.blade.php --}}
<div class="mt-6">
    <h3 class="text-lg font-medium">API Tokens</h3>

    @if($plainTextToken)
        <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded">
            <p class="text-sm text-green-800">
                Copy your new API token. For security, it won't be shown again.
            </p>
            <code class="block mt-2 p-2 bg-white rounded text-sm break-all">
                {{ $plainTextToken }}
            </code>
        </div>
    @endif

    <form wire:submit="createToken" class="mt-4">
        <div class="flex gap-2">
            <x-text-input wire:model="tokenName" placeholder="Token name" class="flex-1" />
            <x-primary-button>Create Token</x-primary-button>
        </div>
        <x-input-error :messages="$errors->get('tokenName')" class="mt-2" />
    </form>

    @if($tokens->count())
        <ul class="mt-4 divide-y">
            @foreach($tokens as $token)
                <li class="py-2 flex justify-between items-center">
                    <span>{{ $token->name }}</span>
                    <button wire:click="deleteToken({{ $token->id }})"
                            class="text-red-600 hover:text-red-800 text-sm">
                        Revoke
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</div>
```

## Authorization Gates

```php
<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('manage-games', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('view-admin', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('edit-tipp', function (User $user, Game $game) {
            return $game->kickoff_at->isFuture();
        });

        Gate::define('view-tipps', function (User $user, Game $game) {
            // Can only view others' tipps after game started
            return $game->kickoff_at->isPast();
        });
    }
}
```

## Security Configuration

### Session Settings

```php
// config/session.php
return [
    'driver' => env('SESSION_DRIVER', 'database'),
    'lifetime' => env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => true,
    'same_site' => 'lax',
];
```

### Sanctum Settings

```php
// config/sanctum.php
return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
    ))),

    'expiration' => null, // Tokens don't expire by default

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
```

### Password Requirements

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Validation\Rules\Password;

public function boot(): void
{
    Password::defaults(function () {
        return Password::min(8)
            ->mixedCase()
            ->numbers()
            ->uncompromised(); // Check against breached password databases
    });
}
```

## Testing Authentication

```php
<?php
// tests/Feature/AuthenticationTest.php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_users_can_authenticate(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('games'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_admin_routes_require_admin_role(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertStatus(403);
    }

    public function test_admins_can_access_admin_routes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertStatus(200);
    }

    public function test_api_requires_sanctum_token(): void
    {
        $response = $this->getJson('/api/my-tipps');
        $response->assertStatus(401);
    }

    public function test_api_works_with_sanctum_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/my-tipps');

        $response->assertStatus(200);
    }
}
```

## Summary

| Feature | Package | Notes |
|---------|---------|-------|
| Web Login/Register | Breeze | Livewire components included |
| Password Reset | Breeze | Email templates included |
| Email Verification | Breeze | Optional, configurable |
| Profile Management | Breeze | Included by default |
| API Tokens | Sanctum | Optional, add if needed |
| Role-based Access | Custom | Simple `is_admin` boolean |
| Session Auth | Breeze | Cookie-based for web |
| Token Auth | Sanctum | Bearer tokens for API |
