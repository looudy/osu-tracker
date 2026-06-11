# osu! Personal Tracker — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a full-stack Laravel 13 web app for osu! players to track scores, view "Almost FC" stats, manage collections and tags, with admin panel.

**Architecture:** Laravel 13 MVC with osu! OAuth via Socialite (primary auth) + email/password fallback via Breeze. Scores are synced manually from osu! API v2. Almost FC feature queries miss_count > 0 beatmaps ranked by fewest misses.

**Tech Stack:** PHP 8.3, Laravel 13.5.0, MariaDB 10.11, DDEV, socialiteproviders/osu, laravel/breeze, raw HTML/CSS/JS (no frontend frameworks).

---

## Pre-flight: Create osu! OAuth Application

**Before writing any code**, register the OAuth app at osu.ppy.sh:

- [ ] Go to https://osu.ppy.sh/home/account/edit → scroll to "OAuth"
- [ ] Click "New OAuth Application"
- [ ] Name: `osu! Personal Tracker`
- [ ] Callback URL: `https://osu-personal-tracker.ddev.site/osu/callback`
- [ ] Save the **Client ID** and **Client Secret** (you provided: `59420` / `UJt6TO2zulAotKSasfYJqr28lzwfuRR6O3WTZ4DL`)

---

## Task 1: DDEV + Laravel Initialization

**Files:**
- Create: `.ddev/config.yaml` (auto-generated)
- Create: entire Laravel skeleton (via composer)
- Modify: `.env`

- [ ] **Step 1: Initialize DDEV**

```bash
cd /Users/kirillpoznak/Documents/university/web/tim_teh_2/osu_project
ddev config --project-name=osu-personal-tracker --project-type=laravel --php-version=8.3 --database=mariadb:10.11
ddev start
```

Expected: DDEV starts and `https://osu-personal-tracker.ddev.site` is accessible.

- [ ] **Step 2: Create Laravel project**

```bash
ddev composer create-project laravel/laravel:^13 . --prefer-dist
```

Expected: `artisan`, `composer.json`, `app/`, `routes/` directories appear.

- [ ] **Step 3: Install required packages**

```bash
ddev composer require laravel/socialite socialiteproviders/osu
ddev composer require laravel/breeze --dev
```

- [ ] **Step 4: Configure .env**

Edit `.env` — set these values:
```
APP_NAME="osu! Personal Tracker"
APP_URL=https://osu-personal-tracker.ddev.site
APP_ENV=local
APP_DEBUG=true

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=db
DB_USERNAME=db
DB_PASSWORD=db

QUEUE_CONNECTION=sync

OSU_CLIENT_ID=59420
OSU_CLIENT_SECRET=UJt6TO2zulAotKSasfYJqr28lzwfuRR6O3WTZ4DL
OSU_REDIRECT_URI=https://osu-personal-tracker.ddev.site/osu/callback
```

Note: `DB_HOST=db` is the DDEV internal MariaDB hostname.

- [ ] **Step 5: Install Breeze (blade)**

```bash
ddev php artisan breeze:install blade --no-interaction
ddev npm install && ddev npm run build
```

- [ ] **Step 6: Verify Laravel is running**

```bash
ddev launch
```

Expected: Laravel welcome page at `https://osu-personal-tracker.ddev.site`

- [ ] **Step 7: Commit**

```bash
git init
git add -A
git commit -m "feat: initialize Laravel 13 + DDEV + Breeze + Socialite"
```

---

## Task 2: Database Migrations

**Files:**
- Create: `database/migrations/0001_01_01_000000_create_users_table.php` (modify existing)
- Create: `database/migrations/2026_06_11_000001_create_beatmaps_table.php`
- Create: `database/migrations/2026_06_11_000002_create_scores_table.php`
- Create: `database/migrations/2026_06_11_000003_create_collections_table.php`
- Create: `database/migrations/2026_06_11_000004_create_beatmap_collection_table.php`
- Create: `database/migrations/2026_06_11_000005_create_tags_table.php`
- Create: `database/migrations/2026_06_11_000006_create_beatmap_tag_table.php`
- Create: `database/migrations/2026_06_11_000007_create_comments_table.php`

- [ ] **Step 1: Modify users migration to add osu! fields**

In the existing users migration file (find it with `ls database/migrations/`), add to `up()`:
```php
// Standard fields already exist from Breeze. Add:
$table->string('osu_id')->nullable()->unique()->after('id');
$table->string('osu_username')->nullable()->after('osu_id');
$table->text('osu_access_token')->nullable()->after('osu_username');
$table->text('osu_refresh_token')->nullable()->after('osu_access_token');
$table->boolean('is_admin')->default(false)->after('osu_refresh_token');
$table->boolean('is_blocked')->default(false)->after('is_admin');
$table->timestamp('blocked_at')->nullable()->after('is_blocked');
$table->timestamp('last_sync_at')->nullable()->after('blocked_at');
// Also make password nullable for osu! OAuth users
$table->string('password')->nullable()->change();
```

- [ ] **Step 2: Create beatmaps migration**

```bash
ddev php artisan make:migration create_beatmaps_table
```

Content of `up()`:
```php
Schema::create('beatmaps', function (Blueprint $table) {
    $table->id();
    $table->bigInteger('beatmap_id')->unique(); // osu! API beatmap ID
    $table->string('title');
    $table->string('artist');
    $table->string('difficulty_name'); // "version" from API
    $table->float('stars'); // difficulty_rating
    $table->integer('length_seconds');
    $table->float('bpm')->nullable();
    $table->integer('circle_count')->nullable();
    $table->float('ar')->nullable(); // approach rate
    $table->float('od')->nullable(); // overall difficulty
    $table->timestamps();
});
```

- [ ] **Step 3: Create scores migration**

```bash
ddev php artisan make:migration create_scores_table
```

Content of `up()`:
```php
Schema::create('scores', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('beatmap_id')->constrained()->onDelete('cascade');
    $table->bigInteger('score');
    $table->float('accuracy'); // 0-100 percentage
    $table->string('rank'); // A, S, SS, B, C, D
    $table->boolean('passed');
    $table->integer('miss_count'); // CRITICAL for Almost FC feature
    $table->dateTime('played_at');
    $table->timestamps();
    // Prevent duplicate imports of the same play
    $table->unique(['user_id', 'beatmap_id', 'played_at', 'score']);
});
```

- [ ] **Step 4: Create collections migration**

```bash
ddev php artisan make:migration create_collections_table
```

Content of `up()`:
```php
Schema::create('collections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('name');
    $table->text('description')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 5: Create beatmap_collection pivot migration**

```bash
ddev php artisan make:migration create_beatmap_collection_table
```

Content of `up()`:
```php
Schema::create('beatmap_collection', function (Blueprint $table) {
    $table->foreignId('collection_id')->constrained()->onDelete('cascade');
    $table->foreignId('beatmap_id')->constrained()->onDelete('cascade');
    $table->primary(['collection_id', 'beatmap_id']);
});
```

- [ ] **Step 6: Create tags migration**

```bash
ddev php artisan make:migration create_tags_table
```

Content of `up()`:
```php
Schema::create('tags', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique(); // stored without # symbol
    $table->timestamps();
});
```

- [ ] **Step 7: Create beatmap_tag pivot migration**

```bash
ddev php artisan make:migration create_beatmap_tag_table
```

Content of `up()`:
```php
Schema::create('beatmap_tag', function (Blueprint $table) {
    $table->foreignId('tag_id')->constrained()->onDelete('cascade');
    $table->foreignId('beatmap_id')->constrained()->onDelete('cascade');
    $table->primary(['tag_id', 'beatmap_id']);
});
```

- [ ] **Step 8: Create comments migration**

```bash
ddev php artisan make:migration create_comments_table
```

Content of `up()`:
```php
Schema::create('comments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('beatmap_id')->constrained()->onDelete('cascade');
    $table->text('content');
    $table->timestamps();
});
```

- [ ] **Step 9: Run all migrations**

```bash
ddev php artisan migrate
```

Expected: All 8+ tables created without errors.

- [ ] **Step 10: Commit**

```bash
git add database/migrations/
git commit -m "feat: add all database migrations"
```

---

## Task 3: Models with Relationships

**Files:**
- Modify: `app/Models/User.php`
- Create: `app/Models/Beatmap.php`
- Create: `app/Models/Score.php`
- Create: `app/Models/Collection.php`
- Create: `app/Models/Tag.php`
- Create: `app/Models/Comment.php`

- [ ] **Step 1: Update User model**

Replace `app/Models/User.php` with:
```php
<?php

/**
 * User model — represents a registered user, either via osu! OAuth or email/password.
 * Stores osu! API tokens and tracks admin/blocked status.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /** @var list<string> Mass-assignable fields */
    protected $fillable = [
        'name',
        'email',
        'password',
        'osu_id',
        'osu_username',
        'osu_access_token',
        'osu_refresh_token',
        'is_admin',
        'is_blocked',
        'blocked_at',
        'last_sync_at',
    ];

    /** @var list<string> Hidden from array/JSON serialization */
    protected $hidden = [
        'password',
        'remember_token',
        'osu_access_token',
        'osu_refresh_token',
    ];

    /** @var array<string, string> Attribute casting */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'blocked_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_blocked' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /** User has many scores imported from osu! API */
    public function scores()
    {
        return $this->hasMany(Score::class);
    }

    /** User can own multiple beatmap collections */
    public function collections()
    {
        return $this->hasMany(Collection::class);
    }

    /** User can leave comments on beatmaps */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /** Check if this user has admin privileges */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /** Check if this user is blocked from the platform */
    public function isBlocked(): bool
    {
        return (bool) $this->is_blocked;
    }

    /**
     * Check if this user's osu! data needs to be re-synced.
     * Re-sync is needed if never synced or if last sync was over 10 minutes ago.
     */
    public function syncNeeded(): bool
    {
        if (!$this->last_sync_at) {
            return true;
        }

        return $this->last_sync_at->lt(now()->subMinutes(10));
    }
}
```

- [ ] **Step 2: Create Beatmap model**

```bash
ddev php artisan make:model Beatmap
```

Replace `app/Models/Beatmap.php` with:
```php
<?php

/**
 * Beatmap model — represents an osu! beatmap (difficulty level of a song).
 * Beatmaps are created automatically when scores are imported from the API.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Beatmap extends Model
{
    use HasFactory;

    protected $fillable = [
        'beatmap_id',
        'title',
        'artist',
        'difficulty_name',
        'stars',
        'length_seconds',
        'bpm',
        'circle_count',
        'ar',
        'od',
    ];

    /** All scores recorded on this beatmap */
    public function scores()
    {
        return $this->hasMany(Score::class);
    }

    /** Collections this beatmap belongs to */
    public function collections()
    {
        return $this->belongsToMany(Collection::class, 'beatmap_collection');
    }

    /** Global tags attached to this beatmap */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'beatmap_tag');
    }

    /** Comments left on this beatmap's detail page */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
```

- [ ] **Step 3: Create Score model**

```bash
ddev php artisan make:model Score
```

Replace `app/Models/Score.php` with:
```php
<?php

/**
 * Score model — a single play attempt on a beatmap by a user.
 * miss_count is the critical field for the Almost FC feature.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Score extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'beatmap_id',
        'score',
        'accuracy',
        'rank',
        'passed',
        'miss_count',
        'played_at',
    ];

    protected function casts(): array
    {
        return [
            'played_at' => 'datetime',
            'passed' => 'boolean',
        ];
    }

    /** The user who set this score */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** The beatmap this score was set on */
    public function beatmap()
    {
        return $this->belongsTo(Beatmap::class);
    }

    /**
     * Scope: only scores for a specific user ID.
     * Usage: Score::forUser($userId)->get()
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: scores that have at least one miss (Almost FC candidates).
     * Usage: Score::almostFc()->get()
     */
    public function scopeAlmostFc($query)
    {
        return $query->where('miss_count', '>', 0);
    }
}
```

- [ ] **Step 4: Create Collection model**

```bash
ddev php artisan make:model Collection
```

Replace `app/Models/Collection.php` with:
```php
<?php

/**
 * Collection model — a named playlist of beatmaps owned by a user.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
    ];

    /** The user who owns this collection */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Beatmaps in this collection (many-to-many) */
    public function beatmaps()
    {
        return $this->belongsToMany(Beatmap::class, 'beatmap_collection');
    }
}
```

- [ ] **Step 5: Create Tag model**

```bash
ddev php artisan make:model Tag
```

Replace `app/Models/Tag.php` with:
```php
<?php

/**
 * Tag model — a global tag that any user can attach to beatmaps.
 * Names are stored without the # symbol (e.g., "dt" not "#dt").
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /** Beatmaps that have this tag attached */
    public function beatmaps()
    {
        return $this->belongsToMany(Beatmap::class, 'beatmap_tag');
    }
}
```

- [ ] **Step 6: Create Comment model**

```bash
ddev php artisan make:model Comment
```

Replace `app/Models/Comment.php` with:
```php
<?php

/**
 * Comment model — a text comment left by a user on a beatmap's detail page.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'beatmap_id',
        'content',
    ];

    /** The user who wrote this comment */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** The beatmap this comment is about */
    public function beatmap()
    {
        return $this->belongsTo(Beatmap::class);
    }
}
```

- [ ] **Step 7: Commit**

```bash
git add app/Models/
git commit -m "feat: add all models with relationships and scopes"
```

---

## Task 4: osu! OAuth Authentication

**Files:**
- Modify: `config/services.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `app/Http/Controllers/Auth/OsuAuthController.php`
- Modify: `routes/web.php`
- Create: `app/Http/Middleware/CheckBlocked.php`
- Modify: `bootstrap/app.php` (or Kernel.php — depends on Laravel 13 structure)

- [ ] **Step 1: Add osu! to services config**

In `config/services.php`, add to the return array:
```php
// osu! OAuth credentials loaded from .env
'osu' => [
    'client_id' => env('OSU_CLIENT_ID'),
    'client_secret' => env('OSU_CLIENT_SECRET'),
    'redirect' => env('OSU_REDIRECT_URI'),
],
```

- [ ] **Step 2: Register Socialite osu! provider in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, in the `boot()` method:
```php
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;

public function boot(): void
{
    // Register the osu! OAuth provider with Laravel Socialite.
    // SocialiteWasCalled fires when Socialite is first used.
    Event::listen(SocialiteWasCalled::class, function ($event) {
        $event->extendSocialite('osu', \SocialiteProviders\Osu\OsuExtendSocialite::class);
    });
}
```

- [ ] **Step 3: Create OsuAuthController**

Create `app/Http/Controllers/Auth/OsuAuthController.php`:
```php
<?php

/**
 * Handles osu! OAuth login flow.
 * Step 1: redirect() sends user to osu! authorization page.
 * Step 2: callback() receives the authorization code and exchanges it for tokens.
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class OsuAuthController extends Controller
{
    /**
     * Redirect the user to the osu! OAuth authorization page.
     * Requests 'identify' scope to get the user's profile data.
     */
    public function redirect()
    {
        return Socialite::driver('osu')
            ->scopes(['identify', 'public'])
            ->redirect();
    }

    /**
     * Handle the callback from osu! after the user authorizes the app.
     * Creates a new user or updates an existing one, then logs them in.
     */
    public function callback()
    {
        try {
            // Exchange authorization code for user data + tokens
            $osuUser = Socialite::driver('osu')->user();
        } catch (\Exception $e) {
            // If OAuth fails (user denied, invalid state, etc.) redirect back
            return redirect()->route('login')
                ->with('error', 'osu! authentication failed. Please try again.');
        }

        // Find or create a local user record matching this osu! account.
        // updateOrCreate avoids duplicate users if they log in multiple times.
        $user = User::updateOrCreate(
            ['osu_id' => (string) $osuUser->getId()],
            [
                'name' => $osuUser->getName(),
                'osu_username' => $osuUser->getNickname(),
                'osu_access_token' => $osuUser->token,
                'osu_refresh_token' => $osuUser->refreshToken,
                'email' => $osuUser->getEmail() ?? $osuUser->getId() . '@osu.placeholder',
            ]
        );

        // Blocked users cannot log in via any method
        if ($user->is_blocked) {
            return redirect()->route('login')
                ->with('error', 'Your account has been blocked. Please contact an administrator.');
        }

        // Log the user in and regenerate session to prevent fixation
        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
```

- [ ] **Step 4: Create CheckBlocked middleware**

Create `app/Http/Middleware/CheckBlocked.php`:
```php
<?php

/**
 * CheckBlocked middleware — runs on every web request for authenticated users.
 * If the user has been blocked by an admin since their last login, force logout.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckBlocked
{
    /**
     * Handle an incoming request.
     * Checks if the currently authenticated user is blocked and logs them out if so.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->is_blocked) {
            // Log out the blocked user and redirect to login with an explanation
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Your account has been blocked.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 5: Register middleware in bootstrap/app.php**

In Laravel 13, middleware is registered in `bootstrap/app.php`. Add `CheckBlocked` to the `web` group:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\CheckBlocked::class,
    ]);
})
```

- [ ] **Step 6: Add osu! OAuth routes**

In `routes/web.php`, add:
```php
use App\Http\Controllers\Auth\OsuAuthController;

// osu! OAuth routes — accessible without authentication
Route::get('/osu/redirect', [OsuAuthController::class, 'redirect'])->name('osu.redirect');
Route::get('/osu/callback', [OsuAuthController::class, 'callback'])->name('osu.callback');
```

- [ ] **Step 7: Add "Login with osu!" button to Breeze login view**

In `resources/views/auth/login.blade.php`, before the submit button, add:
```html
{{-- osu! OAuth login option --}}
<div style="text-align: center; margin: 1rem 0;">
    <a href="{{ route('osu.redirect') }}" class="btn-osu">
        Login with osu!
    </a>
</div>
<hr>
```

- [ ] **Step 8: Test OAuth flow**

```bash
ddev launch /osu/redirect
```

Expected: Browser redirects to `osu.ppy.sh` login. After login, redirects back to `/dashboard`.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Auth/OsuAuthController.php \
        app/Http/Middleware/CheckBlocked.php \
        app/Providers/AppServiceProvider.php \
        config/services.php \
        routes/web.php \
        bootstrap/app.php \
        resources/views/auth/login.blade.php
git commit -m "feat: add osu! OAuth authentication with Socialite"
```

---

## Task 5: osu! API Sync Service (Manual Sync)

**Files:**
- Create: `app/Services/OsuSyncService.php`
- Create: `app/Http/Controllers/SyncController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create OsuSyncService**

Create `app/Services/OsuSyncService.php`:
```php
<?php

/**
 * OsuSyncService — all communication with the osu! API v2.
 * Fetches recent scores for a user and stores new ones in the database.
 * Beatmaps are created on the fly if they don't exist yet.
 */

namespace App\Services;

use App\Models\Beatmap;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OsuSyncService
{
    /** Base URL for all osu! API v2 requests */
    private const API_BASE = 'https://osu.ppy.sh/api/v2';

    /**
     * Fetch and store recent scores for a user.
     * Returns a summary with count of new scores and any errors.
     *
     * @param User $user  The user to sync
     * @param int  $limit Number of recent scores to fetch (max 100 per API)
     * @return array{new_scores: int, errors: string[]}
     */
    public function syncUserScores(User $user, int $limit = 50): array
    {
        // Guard: user must have a valid osu! access token
        if (!$user->osu_access_token) {
            return ['new_scores' => 0, 'errors' => ['No osu! access token. Please login again.']];
        }

        // Fetch recent scores from osu! API
        $response = Http::withToken($user->osu_access_token)
            ->timeout(15)
            ->get(self::API_BASE . "/users/{$user->osu_id}/scores/recent", [
                'limit' => min($limit, 100), // API maximum is 100
                'include_fails' => 1,        // Include failed plays too
            ]);

        // Handle API-level errors
        if ($response->status() === 401) {
            // Token expired — user must re-authenticate via osu! OAuth
            Log::warning("osu! API 401 for user {$user->id} — token expired");
            return ['new_scores' => 0, 'errors' => ['Session expired. Please login with osu! again.']];
        }

        if ($response->status() === 429) {
            // Rate limited — user is syncing too fast
            Log::warning("osu! API 429 for user {$user->id} — rate limited");
            return ['new_scores' => 0, 'errors' => ['Too many requests. Wait a minute before syncing again.']];
        }

        if ($response->failed()) {
            Log::error("osu! API error {$response->status()} for user {$user->id}");
            return ['new_scores' => 0, 'errors' => ["API error: HTTP {$response->status()}"]];
        }

        $scores = $response->json();

        if (!is_array($scores)) {
            return ['new_scores' => 0, 'errors' => ['Unexpected API response format']];
        }

        $newCount = 0;
        $errors = [];

        foreach ($scores as $scoreData) {
            try {
                if ($this->storeScoreIfNew($user, $scoreData)) {
                    $newCount++;
                }
            } catch (\Exception $e) {
                $errors[] = "Failed to store score: {$e->getMessage()}";
                Log::error('Score storage failed', ['error' => $e->getMessage(), 'score' => $scoreData]);
            }
        }

        // Update last sync timestamp so we know when data is fresh
        $user->update(['last_sync_at' => now()]);

        return ['new_scores' => $newCount, 'errors' => $errors];
    }

    /**
     * Store a single score if it hasn't been imported yet.
     * Also creates the beatmap record if it doesn't exist.
     *
     * @param User  $user      The user who set the score
     * @param array $scoreData Raw score object from osu! API
     * @return bool True if a new score was stored, false if it was a duplicate
     */
    private function storeScoreIfNew(User $user, array $scoreData): bool
    {
        // Extract beatmap data nested in the score response
        $beatmapData = $scoreData['beatmap'] ?? null;

        if (!$beatmapData) {
            return false; // Skip scores without beatmap data
        }

        // Create or update the beatmap record from the score's beatmap data
        $beatmap = Beatmap::updateOrCreate(
            ['beatmap_id' => $beatmapData['id']],
            [
                'title' => $beatmapData['beatmapset']['title'] ?? $beatmapData['title'] ?? 'Unknown',
                'artist' => $beatmapData['beatmapset']['artist'] ?? $beatmapData['artist'] ?? 'Unknown',
                'difficulty_name' => $beatmapData['version'] ?? 'Unknown',
                'stars' => $beatmapData['difficulty_rating'] ?? 0,
                'length_seconds' => $beatmapData['total_length'] ?? 0,
                'bpm' => $beatmapData['bpm'] ?? null,
                'circle_count' => $beatmapData['count_circles'] ?? null,
                'ar' => $beatmapData['ar'] ?? null,
                'od' => $beatmapData['accuracy'] ?? null, // "accuracy" is OD in beatmap data
            ]
        );

        // Check if we already have this exact play stored (deduplication)
        $exists = Score::where('user_id', $user->id)
            ->where('beatmap_id', $beatmap->id)
            ->where('played_at', $scoreData['created_at'])
            ->where('score', $scoreData['score'])
            ->exists();

        if ($exists) {
            return false; // Already have this play, skip
        }

        // Extract miss count — the most important field for the Almost FC feature
        $missCount = $scoreData['statistics']['count_miss'] ?? 0;

        // Store the score
        Score::create([
            'user_id' => $user->id,
            'beatmap_id' => $beatmap->id,
            'score' => $scoreData['score'],
            'accuracy' => round(($scoreData['accuracy'] ?? 0) * 100, 2), // API gives 0-1, store as 0-100
            'rank' => $scoreData['rank'] ?? 'D',
            'passed' => $scoreData['passed'] ?? false,
            'miss_count' => $missCount,
            'played_at' => $scoreData['created_at'],
        ]);

        return true;
    }
}
```

- [ ] **Step 2: Create SyncController**

Create `app/Http/Controllers/SyncController.php`:
```php
<?php

/**
 * SyncController — handles manual score synchronization triggered by the user.
 * Calls OsuSyncService and redirects back with a status message.
 */

namespace App\Http\Controllers;

use App\Services\OsuSyncService;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(private OsuSyncService $syncService)
    {
    }

    /**
     * Manually trigger a score sync for the authenticated user.
     * The user must have an osu! account linked.
     */
    public function manualSync(Request $request)
    {
        $user = auth()->user();

        // Only osu! users can sync (email-only users have no osu_id)
        if (!$user->osu_id) {
            return back()->with('error', 'Score sync requires an osu! account. Please login with osu!');
        }

        $result = $this->syncService->syncUserScores($user);

        if (!empty($result['errors'])) {
            return back()->with('error', 'Sync failed: ' . implode(', ', $result['errors']));
        }

        return back()->with('success', "Synced {$result['new_scores']} new score(s).");
    }
}
```

- [ ] **Step 3: Add sync route**

In `routes/web.php`:
```php
use App\Http\Controllers\SyncController;

// Protected: only logged-in users can sync their scores
Route::post('/sync-scores', [SyncController::class, 'manualSync'])
    ->middleware('auth')
    ->name('sync.scores');
```

- [ ] **Step 4: Commit**

```bash
git add app/Services/OsuSyncService.php \
        app/Http/Controllers/SyncController.php \
        routes/web.php
git commit -m "feat: add OsuSyncService and manual sync endpoint"
```

---

## Task 6: Dashboard with Almost FC Feature

**Files:**
- Create: `app/Http/Controllers/DashboardController.php`
- Create: `resources/views/dashboard.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create DashboardController**

Create `app/Http/Controllers/DashboardController.php`:
```php
<?php

/**
 * DashboardController — main page for authenticated users.
 * Shows personal stats, Almost FC leaderboard, and recent scores.
 */

namespace App\Http\Controllers;

use App\Models\Score;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the user's dashboard.
     * Computes aggregated stats and the Almost FC beatmap list.
     */
    public function index()
    {
        $user = auth()->user();

        // Aggregated stats from all imported scores
        $totalPlays = Score::forUser($user->id)->count();
        $totalUniqueMaps = Score::forUser($user->id)->distinct('beatmap_id')->count('beatmap_id');
        $bestAccuracy = Score::forUser($user->id)->max('accuracy') ?? 0;
        $passedCount = Score::forUser($user->id)->where('passed', true)->count();
        $passRate = $totalPlays > 0 ? round(($passedCount / $totalPlays) * 100, 1) : 0;

        /**
         * Almost FC query — beatmaps where user has at least one score with misses > 0.
         * Groups by beatmap to show the BEST attempt (fewest misses, then highest accuracy).
         * Ordered by lowest miss count first — these are the easiest FCs to achieve.
         */
        $almostFcMaps = DB::select("
            SELECT
                b.id,
                b.title,
                b.artist,
                b.difficulty_name,
                b.stars,
                b.beatmap_id AS osu_beatmap_id,
                MIN(s.miss_count) AS best_miss_count,
                MAX(s.accuracy) AS best_accuracy,
                (
                    SELECT s2.score
                    FROM scores s2
                    WHERE s2.beatmap_id = b.id AND s2.user_id = ?
                    ORDER BY s2.miss_count ASC, s2.accuracy DESC
                    LIMIT 1
                ) AS best_score
            FROM scores s
            JOIN beatmaps b ON b.id = s.beatmap_id
            WHERE s.user_id = ? AND s.miss_count > 0
            GROUP BY s.beatmap_id, b.id, b.title, b.artist, b.difficulty_name, b.stars, b.beatmap_id
            ORDER BY best_miss_count ASC
            LIMIT 20
        ", [$user->id, $user->id]);

        // 10 most recent scores for the "Recent Activity" section
        $recentScores = Score::forUser($user->id)
            ->with('beatmap')
            ->orderBy('played_at', 'desc')
            ->limit(10)
            ->get();

        return view('dashboard', compact(
            'user',
            'totalPlays',
            'totalUniqueMaps',
            'bestAccuracy',
            'passRate',
            'almostFcMaps',
            'recentScores'
        ));
    }
}
```

- [ ] **Step 2: Create dashboard view**

Create `resources/views/dashboard.blade.php`:
```html
{{-- Dashboard: main page for authenticated users showing stats and Almost FC list --}}
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="container">

    {{-- Flash messages from sync operations --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    {{-- Profile header with osu! username and sync button --}}
    <div class="card profile-card">
        <div class="profile-info">
            <h1>{{ $user->osu_username ?? $user->name }}</h1>
            @if($user->osu_id)
                <p class="text-muted">osu! ID: {{ $user->osu_id }}</p>
            @endif
            <p class="text-muted">
                Last synced:
                {{ $user->last_sync_at ? $user->last_sync_at->diffForHumans() : 'Never' }}
            </p>
        </div>
        @if($user->osu_id)
        <form action="{{ route('sync.scores') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary" id="sync-btn">
                Sync Scores
            </button>
        </form>
        @endif
    </div>

    {{-- Aggregated stats cards --}}
    <div class="stats-grid">
        <div class="card stat-card">
            <div class="stat-value">{{ $totalUniqueMaps }}</div>
            <div class="stat-label">Unique Maps</div>
        </div>
        <div class="card stat-card">
            <div class="stat-value">{{ $totalPlays }}</div>
            <div class="stat-label">Total Plays</div>
        </div>
        <div class="card stat-card">
            <div class="stat-value">{{ number_format($bestAccuracy, 2) }}%</div>
            <div class="stat-label">Best Accuracy</div>
        </div>
        <div class="card stat-card">
            <div class="stat-value">{{ $passRate }}%</div>
            <div class="stat-label">Pass Rate</div>
        </div>
    </div>

    {{-- Almost FC table — the core feature of this app --}}
    <div class="card">
        <h2>Almost FC — Closest to Full Combo</h2>
        <p class="text-muted">Beatmaps where you had misses. Sorted by fewest misses first.</p>

        @if(count($almostFcMaps) === 0)
            <p>No data yet. <a href="{{ route('sync.scores') }}" onclick="event.preventDefault(); document.getElementById('sync-btn').click()">Sync your scores</a> to see this list.</p>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Beatmap</th>
                    <th>Difficulty</th>
                    <th>Stars</th>
                    <th>Best Misses</th>
                    <th>Best Accuracy</th>
                </tr>
            </thead>
            <tbody>
                @foreach($almostFcMaps as $map)
                <tr>
                    <td>
                        {{-- Link to our local beatmap detail page --}}
                        <a href="{{ route('beatmaps.show', $map->id) }}">
                            {{ $map->artist }} — {{ $map->title }}
                        </a>
                    </td>
                    <td>{{ $map->difficulty_name }}</td>
                    <td>{{ number_format($map->stars, 2) }}★</td>
                    <td class="miss-count {{ $map->best_miss_count == 1 ? 'one-miss' : '' }}">
                        {{ $map->best_miss_count }}
                    </td>
                    <td>{{ number_format($map->best_accuracy, 2) }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Recent scores section --}}
    <div class="card">
        <h2>Recent Scores</h2>
        @if($recentScores->isEmpty())
            <p>No scores imported yet.</p>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Beatmap</th>
                    <th>Accuracy</th>
                    <th>Rank</th>
                    <th>Misses</th>
                    <th>Played</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentScores as $score)
                <tr>
                    <td>
                        <a href="{{ route('beatmaps.show', $score->beatmap->id) }}">
                            {{ $score->beatmap->artist }} — {{ $score->beatmap->title }}
                        </a>
                    </td>
                    <td>{{ number_format($score->accuracy, 2) }}%</td>
                    <td class="rank rank-{{ strtolower($score->rank) }}">{{ $score->rank }}</td>
                    <td>{{ $score->miss_count }}</td>
                    <td>{{ $score->played_at->format('Y-m-d H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
```

- [ ] **Step 3: Update dashboard route**

Replace the existing `/dashboard` route in `routes/web.php`:
```php
use App\Http\Controllers\DashboardController;

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/DashboardController.php \
        resources/views/dashboard.blade.php \
        routes/web.php
git commit -m "feat: add dashboard with Almost FC feature"
```

---

## Task 7: Beatmap List and Detail Pages

**Files:**
- Create: `app/Http/Controllers/BeatmapController.php`
- Create: `resources/views/beatmaps/index.blade.php`
- Create: `resources/views/beatmaps/show.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create BeatmapController**

Create `app/Http/Controllers/BeatmapController.php`:
```php
<?php

/**
 * BeatmapController — public list of all known beatmaps with search and detail page.
 */

namespace App\Http\Controllers;

use App\Models\Beatmap;
use App\Models\Collection;
use App\Models\Tag;
use Illuminate\Http\Request;

class BeatmapController extends Controller
{
    /**
     * Display paginated list of beatmaps with optional search/filter.
     * GET /beatmaps?search=...&tag_id=...&collection_id=...
     */
    public function index(Request $request)
    {
        $query = Beatmap::query();

        // Text search: match title or artist (case-insensitive)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('artist', 'LIKE', "%{$search}%");
            });
        }

        // Filter by tag
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', fn($q) => $q->where('tags.id', $request->tag_id));
        }

        // Filter by collection (only the authenticated user's collections)
        if ($request->filled('collection_id') && auth()->check()) {
            $query->whereHas('collections', fn($q) => $q->where('collections.id', $request->collection_id));
        }

        $beatmaps = $query->orderBy('title')->paginate(20)->withQueryString();

        // Pass all tags and user's collections for the filter dropdowns
        $tags = Tag::orderBy('name')->get();
        $collections = auth()->check()
            ? Collection::where('user_id', auth()->id())->orderBy('name')->get()
            : collect();

        return view('beatmaps.index', compact('beatmaps', 'tags', 'collections'));
    }

    /**
     * Show a single beatmap's detail page with scores, comments, and tags.
     */
    public function show(int $id)
    {
        $beatmap = Beatmap::with(['tags', 'comments.user'])->findOrFail($id);

        // Only show the authenticated user's own scores (privacy)
        $userScores = auth()->check()
            ? $beatmap->scores()->where('user_id', auth()->id())->orderBy('played_at', 'desc')->get()
            : collect();

        // User's collections for the "Add to Collection" dropdown
        $userCollections = auth()->check()
            ? Collection::where('user_id', auth()->id())->get()
            : collect();

        return view('beatmaps.show', compact('beatmap', 'userScores', 'userCollections'));
    }
}
```

- [ ] **Step 2: Create beatmaps index view**

Create `resources/views/beatmaps/index.blade.php`:
```html
{{-- Beatmap list page: shows all known beatmaps with search and filter --}}
@extends('layouts.app')

@section('title', 'Beatmaps')

@section('content')
<div class="container">
    <h1>Beatmaps</h1>

    {{-- Search and filter form --}}
    <form method="GET" action="{{ route('beatmaps.index') }}" class="search-form card">
        <div class="form-row">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search by title or artist..." class="form-input">

            <select name="tag_id" class="form-input">
                <option value="">All Tags</option>
                @foreach($tags as $tag)
                    <option value="{{ $tag->id }}" {{ request('tag_id') == $tag->id ? 'selected' : '' }}>
                        #{{ $tag->name }}
                    </option>
                @endforeach
            </select>

            @auth
            <select name="collection_id" class="form-input">
                <option value="">All Collections</option>
                @foreach($collections as $col)
                    <option value="{{ $col->id }}" {{ request('collection_id') == $col->id ? 'selected' : '' }}>
                        {{ $col->name }}
                    </option>
                @endforeach
            </select>
            @endauth

            <button type="submit" class="btn btn-primary">Search</button>
            <a href="{{ route('beatmaps.index') }}" class="btn btn-secondary">Clear</a>
        </div>
    </form>

    {{-- Results table --}}
    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Artist</th>
                    <th>Difficulty</th>
                    <th>Stars</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($beatmaps as $beatmap)
                <tr>
                    <td>{{ $beatmap->title }}</td>
                    <td>{{ $beatmap->artist }}</td>
                    <td>{{ $beatmap->difficulty_name }}</td>
                    <td>{{ number_format($beatmap->stars, 2) }}★</td>
                    <td>
                        <a href="{{ route('beatmaps.show', $beatmap->id) }}" class="btn btn-sm btn-secondary">
                            View Details
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center">No beatmaps found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pagination --}}
        <div class="pagination-wrapper">
            {{ $beatmaps->links() }}
        </div>
    </div>
</div>
@endsection
```

- [ ] **Step 3: Create beatmap show view**

Create `resources/views/beatmaps/show.blade.php`:
```html
{{-- Beatmap detail page: metadata, user scores, comments, tags --}}
@extends('layouts.app')

@section('title', $beatmap->title)

@section('content')
<div class="container">

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    {{-- Beatmap metadata --}}
    <div class="card">
        <h1>{{ $beatmap->artist }} — {{ $beatmap->title }}</h1>
        <h3 class="text-muted">{{ $beatmap->difficulty_name }}</h3>

        <div class="metadata-grid">
            <div><strong>Stars:</strong> {{ number_format($beatmap->stars, 2) }}★</div>
            <div><strong>Length:</strong> {{ gmdate('i:s', $beatmap->length_seconds) }}</div>
            @if($beatmap->bpm)<div><strong>BPM:</strong> {{ $beatmap->bpm }}</div>@endif
            @if($beatmap->ar)<div><strong>AR:</strong> {{ $beatmap->ar }}</div>@endif
            @if($beatmap->od)<div><strong>OD:</strong> {{ $beatmap->od }}</div>@endif
            @if($beatmap->circle_count)<div><strong>Circles:</strong> {{ $beatmap->circle_count }}</div>@endif
        </div>

        {{-- Link to official osu! page --}}
        <a href="https://osu.ppy.sh/b/{{ $beatmap->beatmap_id }}" target="_blank" class="btn btn-secondary">
            View on osu!
        </a>
    </div>

    {{-- Tags section --}}
    <div class="card">
        <h2>Tags</h2>
        <div class="tags-list">
            @forelse($beatmap->tags as $tag)
                <span class="tag">#{{ $tag->name }}</span>
                @auth
                @if(auth()->user()->isAdmin())
                <form action="{{ route('tags.detach', [$beatmap->id, $tag->id]) }}" method="POST" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-tag-remove" title="Remove tag">×</button>
                </form>
                @endif
                @endauth
            @empty
                <p class="text-muted">No tags yet.</p>
            @endforelse
        </div>

        {{-- Add tag form (any authenticated user can add tags) --}}
        @auth
        <form action="{{ route('tags.attach', $beatmap->id) }}" method="POST" class="inline-form">
            @csrf
            <input type="text" name="tags_input" placeholder="#dt #jumps #technical" class="form-input">
            <button type="submit" class="btn btn-secondary">Add Tags</button>
        </form>
        @endauth
    </div>

    {{-- User's scores on this beatmap --}}
    @auth
    <div class="card">
        <h2>Your Scores</h2>
        @if($userScores->isEmpty())
            <p>You have no scores on this beatmap yet.</p>
        @else
        <table class="data-table">
            <thead>
                <tr><th>Date</th><th>Score</th><th>Accuracy</th><th>Rank</th><th>Misses</th></tr>
            </thead>
            <tbody>
                @foreach($userScores as $score)
                <tr>
                    <td>{{ $score->played_at->format('Y-m-d H:i') }}</td>
                    <td>{{ number_format($score->score) }}</td>
                    <td>{{ number_format($score->accuracy, 2) }}%</td>
                    <td class="rank rank-{{ strtolower($score->rank) }}">{{ $score->rank }}</td>
                    <td>{{ $score->miss_count }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
    @endauth

    {{-- Add to collection --}}
    @auth
    @if($userCollections->isNotEmpty())
    <div class="card">
        <h2>Add to Collection</h2>
        <form action="{{ route('collections.addBeatmap', [$userCollections->first()->id, $beatmap->id]) }}" method="POST" class="inline-form" id="add-to-collection-form">
            @csrf
            <select name="collection_id" class="form-input" onchange="this.form.action='{{ url('/collections') }}/'+this.value+'/beatmaps/{{ $beatmap->id }}'">
                @foreach($userCollections as $col)
                    <option value="{{ $col->id }}">{{ $col->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary">Add</button>
        </form>
    </div>
    @endif
    @endauth

    {{-- Comments section --}}
    <div class="card">
        <h2>Comments</h2>

        @forelse($beatmap->comments as $comment)
        <div class="comment">
            <div class="comment-header">
                <strong>{{ $comment->user->name }}</strong>
                <span class="text-muted">{{ $comment->created_at->diffForHumans() }}</span>

                {{-- Edit/delete buttons for comment owner or admin --}}
                @auth
                @if(auth()->id() === $comment->user_id || auth()->user()->isAdmin())
                <div class="comment-actions">
                    <a href="{{ route('comments.edit', $comment->id) }}" class="btn btn-sm btn-secondary">Edit</a>
                    <form action="{{ route('comments.destroy', $comment->id) }}" method="POST" style="display:inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger"
                                onclick="return confirm('Delete this comment?')">Delete</button>
                    </form>
                </div>
                @endif
                @endauth
            </div>
            <p>{{ $comment->content }}</p>
        </div>
        @empty
            <p class="text-muted">No comments yet. Be the first!</p>
        @endforelse

        {{-- Add comment form (authenticated users only) --}}
        @auth
        <form action="{{ route('comments.store', $beatmap->id) }}" method="POST" class="comment-form">
            @csrf
            <textarea name="content" class="form-input" placeholder="Write a comment..." rows="3" required></textarea>
            @error('content')<div class="form-error">{{ $message }}</div>@enderror
            <button type="submit" class="btn btn-primary">Post Comment</button>
        </form>
        @else
        <p><a href="{{ route('login') }}">Login</a> to post a comment.</p>
        @endauth
    </div>
</div>
@endsection
```

- [ ] **Step 4: Add beatmap routes**

In `routes/web.php`:
```php
use App\Http\Controllers\BeatmapController;

// Public: beatmap list and detail (no auth required to view)
Route::get('/beatmaps', [BeatmapController::class, 'index'])->name('beatmaps.index');
Route::get('/beatmaps/{id}', [BeatmapController::class, 'show'])->name('beatmaps.show');
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/BeatmapController.php \
        resources/views/beatmaps/ \
        routes/web.php
git commit -m "feat: add beatmap list and detail pages"
```

---

## Task 8: Comments System

**Files:**
- Create: `app/Http/Controllers/CommentController.php`
- Create: `app/Policies/CommentPolicy.php`
- Modify: `app/Providers/AuthServiceProvider.php` (or AppServiceProvider in Laravel 13)
- Modify: `routes/web.php`
- Create: `resources/views/comments/edit.blade.php`

- [ ] **Step 1: Create CommentController**

Create `app/Http/Controllers/CommentController.php`:
```php
<?php

/**
 * CommentController — CRUD operations for beatmap comments.
 * Only comment owners and admins can edit/delete.
 */

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Store a new comment on a beatmap.
     */
    public function store(Request $request, int $beatmapId)
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        Comment::create([
            'user_id' => auth()->id(),
            'beatmap_id' => $beatmapId,
            'content' => $validated['content'],
        ]);

        return redirect()->route('beatmaps.show', $beatmapId)
            ->with('success', 'Comment posted.');
    }

    /**
     * Show the edit form for an existing comment.
     */
    public function edit(int $id)
    {
        $comment = Comment::findOrFail($id);

        // Only owner or admin may edit
        $this->authorize('update', $comment);

        return view('comments.edit', compact('comment'));
    }

    /**
     * Update an existing comment's content.
     */
    public function update(Request $request, int $id)
    {
        $comment = Comment::findOrFail($id);
        $this->authorize('update', $comment);

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $comment->update(['content' => $validated['content']]);

        return redirect()->route('beatmaps.show', $comment->beatmap_id)
            ->with('success', 'Comment updated.');
    }

    /**
     * Delete a comment.
     */
    public function destroy(int $id)
    {
        $comment = Comment::findOrFail($id);
        $this->authorize('delete', $comment);

        $beatmapId = $comment->beatmap_id;
        $comment->delete();

        return redirect()->route('beatmaps.show', $beatmapId)
            ->with('success', 'Comment deleted.');
    }
}
```

- [ ] **Step 2: Create CommentPolicy**

```bash
ddev php artisan make:policy CommentPolicy --model=Comment
```

Replace `app/Policies/CommentPolicy.php`:
```php
<?php

/**
 * CommentPolicy — authorization rules for comment actions.
 * Both comment owners and admins can update/delete.
 */

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /** Any authenticated user can view comments */
    public function view(?User $user, Comment $comment): bool
    {
        return true;
    }

    /** Only comment owner or admin can edit */
    public function update(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id || $user->isAdmin();
    }

    /** Only comment owner or admin can delete */
    public function delete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id || $user->isAdmin();
    }
}
```

- [ ] **Step 3: Register CommentPolicy in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, in `boot()`:
```php
use App\Models\Comment;
use App\Policies\CommentPolicy;
use Illuminate\Support\Facades\Gate;

// Register comment policy — controls who can edit/delete comments
Gate::policy(Comment::class, CommentPolicy::class);
```

- [ ] **Step 4: Create comment edit view**

Create `resources/views/comments/edit.blade.php`:
```html
{{-- Edit comment form --}}
@extends('layouts.app')

@section('title', 'Edit Comment')

@section('content')
<div class="container">
    <div class="card" style="max-width: 600px;">
        <h1>Edit Comment</h1>

        <form action="{{ route('comments.update', $comment->id) }}" method="POST">
            @csrf
            @method('PUT')
            <textarea name="content" class="form-input" rows="5" required>{{ old('content', $comment->content) }}</textarea>
            @error('content')<div class="form-error">{{ $message }}</div>@enderror

            <div style="margin-top: 1rem;">
                <button type="submit" class="btn btn-primary">Update Comment</button>
                <a href="{{ route('beatmaps.show', $comment->beatmap_id) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
```

- [ ] **Step 5: Add comment routes**

In `routes/web.php`:
```php
use App\Http\Controllers\CommentController;

// Comments — require authentication
Route::middleware('auth')->group(function () {
    Route::post('/beatmaps/{beatmapId}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::get('/comments/{id}/edit', [CommentController::class, 'edit'])->name('comments.edit');
    Route::put('/comments/{id}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{id}', [CommentController::class, 'destroy'])->name('comments.destroy');
});
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/CommentController.php \
        app/Policies/CommentPolicy.php \
        app/Providers/AppServiceProvider.php \
        resources/views/comments/ \
        routes/web.php
git commit -m "feat: add comments system with policy authorization"
```

---

## Task 9: Tags System

**Files:**
- Create: `app/Http/Controllers/TagController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create TagController**

Create `app/Http/Controllers/TagController.php`:
```php
<?php

/**
 * TagController — manage global tags on beatmaps.
 * Any authenticated user can add tags. Only admins can remove/delete.
 */

namespace App\Http\Controllers;

use App\Models\Beatmap;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Parse a tag string like "#dt #jumps #technical" and attach to beatmap.
     * Creates tags that don't exist yet (global tag pool).
     */
    public function attach(Request $request, int $beatmapId)
    {
        $request->validate([
            'tags_input' => ['required', 'string', 'max:200'],
        ]);

        $beatmap = Beatmap::findOrFail($beatmapId);

        // Extract tag names from "#dt #jumps" format using regex
        preg_match_all('/#(\w+)/', $request->tags_input, $matches);
        $tagNames = array_unique($matches[1]);

        if (empty($tagNames)) {
            return back()->with('error', 'No valid tags found. Format: #tagname');
        }

        // Find or create each tag and collect their IDs
        $tagIds = [];
        foreach ($tagNames as $name) {
            $tag = Tag::firstOrCreate(['name' => strtolower($name)]);
            $tagIds[] = $tag->id;
        }

        // syncWithoutDetaching: adds new tags without removing existing ones
        $beatmap->tags()->syncWithoutDetaching($tagIds);

        return back()->with('success', 'Tags added successfully.');
    }

    /**
     * Remove a tag from a specific beatmap (admin only).
     */
    public function detach(int $beatmapId, int $tagId)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Admin access required.');

        $beatmap = Beatmap::findOrFail($beatmapId);
        $beatmap->tags()->detach($tagId);

        return back()->with('success', 'Tag removed from beatmap.');
    }

    /**
     * Delete a tag globally — removes it from all beatmaps (admin only).
     */
    public function destroy(int $tagId)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Admin access required.');

        Tag::findOrFail($tagId)->delete();

        return back()->with('success', 'Tag deleted globally.');
    }
}
```

- [ ] **Step 2: Add tag routes**

In `routes/web.php`:
```php
use App\Http\Controllers\TagController;

Route::middleware('auth')->group(function () {
    Route::post('/beatmaps/{beatmapId}/tags', [TagController::class, 'attach'])->name('tags.attach');
    Route::delete('/beatmaps/{beatmapId}/tags/{tagId}', [TagController::class, 'detach'])->name('tags.detach');
    Route::delete('/admin/tags/{tagId}', [TagController::class, 'destroy'])->name('tags.destroy');
});
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/TagController.php routes/web.php
git commit -m "feat: add global tags system"
```

---

## Task 10: Collections System

**Files:**
- Create: `app/Http/Controllers/CollectionController.php`
- Create: `resources/views/collections/index.blade.php`
- Create: `resources/views/collections/create.blade.php`
- Create: `resources/views/collections/edit.blade.php`
- Create: `resources/views/collections/show.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create CollectionController**

Create `app/Http/Controllers/CollectionController.php`:
```php
<?php

/**
 * CollectionController — manage user's beatmap collections.
 * Each collection belongs to a user; only the owner can modify it.
 */

namespace App\Http\Controllers;

use App\Models\Beatmap;
use App\Models\Collection;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    /** List all of the authenticated user's collections */
    public function index()
    {
        $collections = Collection::where('user_id', auth()->id())
            ->withCount('beatmaps')
            ->orderBy('name')
            ->get();

        return view('collections.index', compact('collections'));
    }

    /** Show form to create a new collection */
    public function create()
    {
        return view('collections.create');
    }

    /** Store a new collection */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        Collection::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('collections.index')->with('success', 'Collection created.');
    }

    /** Show form to edit a collection */
    public function edit(int $id)
    {
        $collection = Collection::where('user_id', auth()->id())->findOrFail($id);

        return view('collections.edit', compact('collection'));
    }

    /** Update collection name/description */
    public function update(Request $request, int $id)
    {
        $collection = Collection::where('user_id', auth()->id())->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $collection->update($validated);

        return redirect()->route('collections.index')->with('success', 'Collection updated.');
    }

    /** Delete a collection (and its beatmap associations) */
    public function destroy(int $id)
    {
        $collection = Collection::where('user_id', auth()->id())->findOrFail($id);
        $collection->delete();

        return redirect()->route('collections.index')->with('success', 'Collection deleted.');
    }

    /** Show all beatmaps in a collection */
    public function show(int $id)
    {
        $collection = Collection::where('user_id', auth()->id())
            ->with('beatmaps.tags')
            ->findOrFail($id);

        return view('collections.show', compact('collection'));
    }

    /** Add a beatmap to a collection */
    public function addBeatmap(Request $request, int $collectionId, int $beatmapId)
    {
        $collection = Collection::where('user_id', auth()->id())->findOrFail($collectionId);
        $beatmap = Beatmap::findOrFail($beatmapId);

        // syncWithoutDetaching: add without removing others
        $collection->beatmaps()->syncWithoutDetaching([$beatmap->id]);

        return back()->with('success', "Added to \"{$collection->name}\".");
    }

    /** Remove a beatmap from a collection */
    public function removeBeatmap(int $collectionId, int $beatmapId)
    {
        $collection = Collection::where('user_id', auth()->id())->findOrFail($collectionId);
        $collection->beatmaps()->detach($beatmapId);

        return back()->with('success', 'Removed from collection.');
    }
}
```

- [ ] **Step 2: Create collection views**

Create `resources/views/collections/index.blade.php`:
```html
{{-- List all user collections --}}
@extends('layouts.app')
@section('title', 'My Collections')
@section('content')
<div class="container">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>My Collections</h1>
        <a href="{{ route('collections.create') }}" class="btn btn-primary">New Collection</a>
    </div>

    <div class="card">
        @forelse($collections as $collection)
        <div class="collection-item">
            <div>
                <a href="{{ route('collections.show', $collection->id) }}"><strong>{{ $collection->name }}</strong></a>
                <span class="text-muted">({{ $collection->beatmaps_count }} beatmaps)</span>
                @if($collection->description)<p class="text-muted">{{ $collection->description }}</p>@endif
            </div>
            <div class="actions">
                <a href="{{ route('collections.edit', $collection->id) }}" class="btn btn-sm btn-secondary">Edit</a>
                <form action="{{ route('collections.destroy', $collection->id) }}" method="POST" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger"
                            onclick="return confirm('Delete this collection?')">Delete</button>
                </form>
            </div>
        </div>
        @empty
        <p>No collections yet. <a href="{{ route('collections.create') }}">Create one!</a></p>
        @endforelse
    </div>
</div>
@endsection
```

Create `resources/views/collections/create.blade.php`:
```html
{{-- Create new collection form --}}
@extends('layouts.app')
@section('title', 'New Collection')
@section('content')
<div class="container">
    <div class="card" style="max-width:500px;">
        <h1>New Collection</h1>
        <form action="{{ route('collections.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-input" required maxlength="100">
                @error('name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label>Description (optional)</label>
                <textarea name="description" class="form-input" maxlength="500">{{ old('description') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Create</button>
            <a href="{{ route('collections.index') }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
```

Create `resources/views/collections/edit.blade.php`:
```html
{{-- Edit collection form --}}
@extends('layouts.app')
@section('title', 'Edit Collection')
@section('content')
<div class="container">
    <div class="card" style="max-width:500px;">
        <h1>Edit Collection</h1>
        <form action="{{ route('collections.update', $collection->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="{{ old('name', $collection->name) }}" class="form-input" required>
                @error('name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-input">{{ old('description', $collection->description) }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('collections.index') }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
```

Create `resources/views/collections/show.blade.php`:
```html
{{-- Show beatmaps in a collection --}}
@extends('layouts.app')
@section('title', $collection->name)
@section('content')
<div class="container">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="card">
        <h1>{{ $collection->name }}</h1>
        @if($collection->description)<p>{{ $collection->description }}</p>@endif

        <table class="data-table">
            <thead>
                <tr><th>Title</th><th>Artist</th><th>Stars</th><th>Tags</th><th>Action</th></tr>
            </thead>
            <tbody>
                @forelse($collection->beatmaps as $beatmap)
                <tr>
                    <td><a href="{{ route('beatmaps.show', $beatmap->id) }}">{{ $beatmap->title }}</a></td>
                    <td>{{ $beatmap->artist }}</td>
                    <td>{{ number_format($beatmap->stars, 2) }}★</td>
                    <td>
                        @foreach($beatmap->tags as $tag)<span class="tag">#{{ $tag->name }}</span>@endforeach
                    </td>
                    <td>
                        <form action="{{ route('collections.removeBeatmap', [$collection->id, $beatmap->id]) }}" method="POST" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5">No beatmaps in this collection.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
```

- [ ] **Step 3: Add collection routes**

In `routes/web.php`:
```php
use App\Http\Controllers\CollectionController;

Route::middleware('auth')->group(function () {
    Route::resource('collections', CollectionController::class)->except(['show']);
    Route::get('/collections/{id}/beatmaps', [CollectionController::class, 'show'])->name('collections.show');
    Route::post('/collections/{collectionId}/beatmaps/{beatmapId}', [CollectionController::class, 'addBeatmap'])->name('collections.addBeatmap');
    Route::delete('/collections/{collectionId}/beatmaps/{beatmapId}', [CollectionController::class, 'removeBeatmap'])->name('collections.removeBeatmap');
});
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/CollectionController.php \
        resources/views/collections/ \
        routes/web.php
git commit -m "feat: add collections system"
```

---

## Task 11: Admin Panel

**Files:**
- Create: `app/Http/Middleware/AdminMiddleware.php`
- Create: `app/Http/Controllers/AdminController.php`
- Create: `resources/views/admin/users.blade.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create AdminMiddleware**

Create `app/Http/Middleware/AdminMiddleware.php`:
```php
<?php

/**
 * AdminMiddleware — protects admin routes from non-admin users.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Register admin middleware alias in bootstrap/app.php**

In `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'admin' => \App\Http\Middleware\AdminMiddleware::class,
    ]);
    $middleware->web(append: [
        \App\Http\Middleware\CheckBlocked::class,
    ]);
})
```

- [ ] **Step 3: Create AdminController**

Create `app/Http/Controllers/AdminController.php`:
```php
<?php

/**
 * AdminController — user management and content moderation for admins.
 */

namespace App\Http\Controllers;

use App\Models\Beatmap;
use App\Models\Comment;
use App\Models\Tag;
use App\Models\User;

class AdminController extends Controller
{
    /** List all users for the admin panel */
    public function users()
    {
        $users = User::orderBy('created_at', 'desc')->paginate(20);

        return view('admin.users', compact('users'));
    }

    /** Block a user — prevents them from logging in */
    public function block(int $userId)
    {
        $user = User::findOrFail($userId);

        // Prevent admins from blocking themselves
        abort_if($user->id === auth()->id(), 400, 'You cannot block yourself.');

        $user->update([
            'is_blocked' => true,
            'blocked_at' => now(),
        ]);

        return back()->with('success', "User \"{$user->name}\" has been blocked.");
    }

    /** Unblock a user — restores their access */
    public function unblock(int $userId)
    {
        $user = User::findOrFail($userId);
        $user->update([
            'is_blocked' => false,
            'blocked_at' => null,
        ]);

        return back()->with('success', "User \"{$user->name}\" has been unblocked.");
    }

    /** Force delete a beatmap and all its associated scores/comments */
    public function deleteBeatmap(int $beatmapId)
    {
        Beatmap::findOrFail($beatmapId)->delete();

        return back()->with('success', 'Beatmap deleted.');
    }

    /** Delete any comment (admin moderation) */
    public function deleteComment(int $commentId)
    {
        Comment::findOrFail($commentId)->delete();

        return back()->with('success', 'Comment deleted.');
    }

    /** Delete a global tag and remove it from all beatmaps */
    public function deleteTag(int $tagId)
    {
        Tag::findOrFail($tagId)->delete();

        return back()->with('success', 'Tag deleted.');
    }
}
```

- [ ] **Step 4: Create admin users view**

Create `resources/views/admin/users.blade.php`:
```html
{{-- Admin panel: list all users with block/unblock actions --}}
@extends('layouts.app')
@section('title', 'Admin — Users')
@section('content')
<div class="container">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <h1>Admin Panel — Users</h1>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>osu! Username</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr class="{{ $user->is_blocked ? 'row-blocked' : '' }}">
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}{{ $user->is_admin ? ' (admin)' : '' }}</td>
                    <td>{{ $user->osu_username ?? '—' }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        @if($user->is_blocked)
                            <span class="badge badge-danger">Blocked</span>
                        @else
                            <span class="badge badge-success">Active</span>
                        @endif
                    </td>
                    <td>
                        @if($user->id !== auth()->id())
                            @if($user->is_blocked)
                                <form action="{{ route('admin.unblock', $user->id) }}" method="POST" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">Unblock</button>
                                </form>
                            @else
                                <form action="{{ route('admin.block', $user->id) }}" method="POST" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Block {{ $user->name }}?')">Block</button>
                                </form>
                            @endif
                        @else
                            <span class="text-muted">You</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        {{ $users->links() }}
    </div>
</div>
@endsection
```

- [ ] **Step 5: Add admin routes**

In `routes/web.php`:
```php
use App\Http\Controllers\AdminController;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::post('/users/{id}/block', [AdminController::class, 'block'])->name('block');
    Route::post('/users/{id}/unblock', [AdminController::class, 'unblock'])->name('unblock');
    Route::delete('/beatmaps/{id}', [AdminController::class, 'deleteBeatmap'])->name('deleteBeatmap');
    Route::delete('/comments/{id}', [AdminController::class, 'deleteComment'])->name('deleteComment');
    Route::delete('/tags/{id}', [AdminController::class, 'deleteTag'])->name('deleteTag');
});
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/AdminMiddleware.php \
        app/Http/Controllers/AdminController.php \
        resources/views/admin/ \
        bootstrap/app.php \
        routes/web.php
git commit -m "feat: add admin panel with user block/unblock"
```

---

## Task 12: Master Layout and CSS

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Create: `public/css/style.css`

- [ ] **Step 1: Create master layout**

Replace `resources/views/layouts/app.blade.php`:
```html
<!DOCTYPE html>
{{-- Master layout for all pages. Provides navigation, flash messages, and CSS. --}}
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'osu! Tracker') — osu! Personal Tracker</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>

{{-- Navigation bar --}}
<nav class="navbar">
    <a href="{{ route('beatmaps.index') }}" class="navbar-brand">osu! Tracker</a>

    <ul class="navbar-links">
        <li><a href="{{ route('beatmaps.index') }}">Beatmaps</a></li>

        @auth
            <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('collections.index') }}">Collections</a></li>

            {{-- Admin link only visible to admins --}}
            @if(auth()->user()->isAdmin())
                <li><a href="{{ route('admin.users') }}" class="nav-admin">Admin</a></li>
            @endif

            <li>
                <form action="{{ route('logout') }}" method="POST" style="display:inline">
                    @csrf
                    <button type="submit" class="btn-nav-logout">Logout ({{ auth()->user()->osu_username ?? auth()->user()->name }})</button>
                </form>
            </li>
        @else
            <li><a href="{{ route('login') }}">Login</a></li>
            <li><a href="{{ route('register') }}">Register</a></li>
        @endauth
    </ul>
</nav>

{{-- Main content area --}}
<main class="main-content">
    @yield('content')
</main>

<footer class="footer">
    <p>osu! Personal Tracker — built with Laravel 13</p>
</footer>

<script>
    // Show loading state on sync button to prevent double-clicks
    document.addEventListener('DOMContentLoaded', function () {
        const syncBtn = document.getElementById('sync-btn');
        if (syncBtn) {
            syncBtn.closest('form').addEventListener('submit', function () {
                syncBtn.disabled = true;
                syncBtn.textContent = 'Syncing...';
            });
        }

        // Confirm dialogs for delete actions are handled inline via onclick
    });
</script>
</body>
</html>
```

- [ ] **Step 2: Create CSS file**

Create `public/css/style.css`:
```css
/* osu! Personal Tracker — main stylesheet */
/* Uses raw CSS with no frameworks. System fonts for fast load. */

/* ---- Reset ---- */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, -apple-system, sans-serif; font-size: 16px; line-height: 1.5; background: #f5f5f5; color: #333; }
a { color: #e94c6c; text-decoration: none; }
a:hover { text-decoration: underline; }

/* ---- Layout ---- */
.container { max-width: 1100px; margin: 0 auto; padding: 1.5rem 1rem; }
.main-content { min-height: calc(100vh - 120px); }

/* ---- Navbar ---- */
.navbar { background: #1a1a2e; color: #fff; display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; height: 56px; }
.navbar-brand { color: #e94c6c; font-size: 1.3rem; font-weight: bold; }
.navbar-links { list-style: none; display: flex; gap: 1.5rem; align-items: center; }
.navbar-links a { color: #ddd; font-size: 0.95rem; }
.navbar-links a:hover { color: #fff; text-decoration: none; }
.nav-admin { color: #f0ad4e !important; }
.btn-nav-logout { background: none; border: 1px solid #555; color: #ddd; padding: 0.3rem 0.8rem; border-radius: 4px; cursor: pointer; font-size: 0.9rem; }
.btn-nav-logout:hover { background: #333; }

/* ---- Cards ---- */
.card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; }

/* ---- Buttons ---- */
.btn { display: inline-block; padding: 0.5rem 1.2rem; border-radius: 5px; border: none; cursor: pointer; font-size: 0.95rem; font-family: inherit; text-align: center; }
.btn:hover { text-decoration: none; filter: brightness(0.9); }
.btn-primary { background: #e94c6c; color: #fff; }
.btn-secondary { background: #6c757d; color: #fff; }
.btn-success { background: #28a745; color: #fff; }
.btn-danger { background: #dc3545; color: #fff; }
.btn-sm { padding: 0.3rem 0.7rem; font-size: 0.85rem; }
.btn-osu { background: #e94c6c; color: #fff; padding: 0.6rem 1.5rem; border-radius: 5px; display: inline-block; font-size: 1rem; }

/* ---- Tables ---- */
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e0e0e0; }
.data-table th { background: #f8f9fa; font-weight: 600; }
.data-table tbody tr:hover { background: #fafafa; }
.row-blocked { opacity: 0.6; background: #fff5f5 !important; }

/* ---- Forms ---- */
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; }
.form-input { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #ccc; border-radius: 4px; font-size: 0.95rem; font-family: inherit; }
.form-error { color: #dc3545; font-size: 0.875rem; margin-top: 0.25rem; }
.search-form .form-row { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }
.search-form .form-input { width: auto; flex: 1; min-width: 150px; }
.inline-form { display: flex; gap: 0.5rem; align-items: center; margin-top: 1rem; flex-wrap: wrap; }

/* ---- Stats grid ---- */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.stat-card { text-align: center; }
.stat-value { font-size: 2rem; font-weight: bold; color: #e94c6c; }
.stat-label { color: #666; font-size: 0.9rem; }

/* ---- Metadata grid for beatmap details ---- */
.metadata-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem; margin: 1rem 0; }

/* ---- Comments ---- */
.comment { border-bottom: 1px solid #e0e0e0; padding: 1rem 0; }
.comment:last-child { border-bottom: none; }
.comment-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem; }
.comment-actions { margin-left: auto; display: flex; gap: 0.5rem; }
.comment-form { margin-top: 1.5rem; }
.comment-form textarea { margin-bottom: 0.75rem; }

/* ---- Tags ---- */
.tags-list { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; }
.tag { background: #e0e7ff; color: #3730a3; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.85rem; }
.btn-tag-remove { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 0.8rem; padding: 0 0.2rem; }

/* ---- Profile card ---- */
.profile-card { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
.profile-info h1 { margin-bottom: 0.25rem; }

/* ---- Alerts ---- */
.alert { padding: 0.75rem 1rem; border-radius: 5px; margin-bottom: 1rem; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

/* ---- Badges ---- */
.badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
.badge-danger { background: #f8d7da; color: #721c24; }

/* ---- Rank colors ---- */
.rank { font-weight: bold; }
.rank-ss { color: #ffd700; }
.rank-s { color: #ffa500; }
.rank-a { color: #28a745; }
.rank-b { color: #007bff; }
.rank-c { color: #6c757d; }
.rank-d { color: #dc3545; }
.one-miss { color: #e94c6c; font-weight: bold; }

/* ---- Collections ---- */
.collection-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 0.75rem 0; border-bottom: 1px solid #e0e0e0; }
.collection-item:last-child { border-bottom: none; }
.collection-item .actions { display: flex; gap: 0.5rem; }

/* ---- Footer ---- */
.footer { text-align: center; padding: 1rem; color: #999; font-size: 0.85rem; border-top: 1px solid #e0e0e0; margin-top: 2rem; }

/* ---- Pagination ---- */
.pagination-wrapper { margin-top: 1rem; display: flex; justify-content: center; }

/* ---- Utility ---- */
.text-muted { color: #6c757d; }
.text-center { text-align: center; }

/* ---- Responsive ---- */
@media (max-width: 768px) {
    .navbar { flex-direction: column; height: auto; padding: 0.75rem; gap: 0.5rem; }
    .navbar-links { gap: 0.75rem; flex-wrap: wrap; justify-content: center; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .profile-card { flex-direction: column; }
    .data-table { font-size: 0.85rem; }
    .data-table th, .data-table td { padding: 0.5rem; }
}
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/app.blade.php public/css/style.css
git commit -m "feat: add master layout and CSS styling"
```

---

## Task 13: Seeders and Factories

**Files:**
- Modify: `database/factories/UserFactory.php`
- Create: `database/factories/BeatmapFactory.php`
- Create: `database/factories/ScoreFactory.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Create BeatmapFactory**

```bash
ddev php artisan make:factory BeatmapFactory --model=Beatmap
```

Replace `database/factories/BeatmapFactory.php`:
```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BeatmapFactory extends Factory
{
    public function definition(): array
    {
        return [
            'beatmap_id' => $this->faker->unique()->numberBetween(100000, 9999999),
            'title' => $this->faker->words(3, true),
            'artist' => $this->faker->name(),
            'difficulty_name' => $this->faker->randomElement(['Easy', 'Normal', 'Hard', 'Insane', 'Expert']),
            'stars' => $this->faker->randomFloat(2, 1.0, 9.5),
            'length_seconds' => $this->faker->numberBetween(60, 360),
            'bpm' => $this->faker->numberBetween(100, 250),
            'circle_count' => $this->faker->numberBetween(50, 500),
            'ar' => $this->faker->randomFloat(1, 5.0, 10.0),
            'od' => $this->faker->randomFloat(1, 5.0, 10.0),
        ];
    }
}
```

- [ ] **Step 2: Create ScoreFactory**

```bash
ddev php artisan make:factory ScoreFactory --model=Score
```

Replace `database/factories/ScoreFactory.php`:
```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ScoreFactory extends Factory
{
    public function definition(): array
    {
        $missCount = $this->faker->numberBetween(0, 20);

        return [
            // user_id and beatmap_id should be passed explicitly in seeders
            'score' => $this->faker->numberBetween(100000, 10000000),
            'accuracy' => $this->faker->randomFloat(2, 60.0, 100.0),
            'rank' => $this->faker->randomElement(['SS', 'S', 'A', 'B', 'C', 'D']),
            'passed' => $missCount === 0 ? $this->faker->boolean(90) : $this->faker->boolean(70),
            'miss_count' => $missCount,
            'played_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
```

- [ ] **Step 3: Update DatabaseSeeder**

Replace `database/seeders/DatabaseSeeder.php`:
```php
<?php

/**
 * DatabaseSeeder — creates test data for development.
 * Run: ddev php artisan db:seed
 */

namespace Database\Seeders;

use App\Models\Beatmap;
use App\Models\Score;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create the admin user (login with admin@osu.test / password)
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@osu.test',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'osu_username' => 'AdminUser',
        ]);

        // Create a test regular user
        $testUser = User::factory()->create([
            'name' => 'TestPlayer',
            'email' => 'player@osu.test',
            'password' => Hash::make('password'),
            'osu_username' => 'TestPlayer',
        ]);

        // Create 20 sample beatmaps
        $beatmaps = Beatmap::factory()->count(20)->create();

        // Create 50 scores for the test user across random beatmaps
        foreach ($beatmaps->random(15) as $beatmap) {
            // 2-5 scores per beatmap to simulate retry behavior
            $attempts = rand(2, 5);
            for ($i = 0; $i < $attempts; $i++) {
                Score::factory()->create([
                    'user_id' => $testUser->id,
                    'beatmap_id' => $beatmap->id,
                ]);
            }
        }

        $this->command->info('Seeded: admin@osu.test, player@osu.test (password: "password"), 20 beatmaps, 50+ scores');
    }
}
```

- [ ] **Step 4: Run seeders**

```bash
ddev php artisan db:seed
```

Expected output: "Seeded: admin@osu.test, player@osu.test ..."

- [ ] **Step 5: Commit**

```bash
git add database/factories/ database/seeders/
git commit -m "feat: add factories and database seeder with test data"
```

---

## Task 14: Error Pages and Final Polish

**Files:**
- Create: `resources/views/errors/403.blade.php`
- Create: `resources/views/errors/404.blade.php`

- [ ] **Step 1: Create error pages**

Create `resources/views/errors/403.blade.php`:
```html
@extends('layouts.app')
@section('title', '403 — Forbidden')
@section('content')
<div class="container" style="text-align:center; padding-top:4rem;">
    <h1 style="font-size:4rem; color:#e94c6c;">403</h1>
    <h2>Access Denied</h2>
    <p class="text-muted">You don't have permission to view this page.</p>
    <a href="{{ url('/') }}" class="btn btn-primary" style="margin-top:1rem;">Go Home</a>
</div>
@endsection
```

Create `resources/views/errors/404.blade.php`:
```html
@extends('layouts.app')
@section('title', '404 — Not Found')
@section('content')
<div class="container" style="text-align:center; padding-top:4rem;">
    <h1 style="font-size:4rem; color:#e94c6c;">404</h1>
    <h2>Page Not Found</h2>
    <p class="text-muted">The page you're looking for doesn't exist.</p>
    <a href="{{ url('/') }}" class="btn btn-primary" style="margin-top:1rem;">Go Home</a>
</div>
@endsection
```

- [ ] **Step 2: Add homepage redirect to beatmaps index**

In `routes/web.php`, replace the existing welcome route:
```php
Route::get('/', function () {
    // Redirect root URL to beatmaps list as the default landing page
    return redirect()->route('beatmaps.index');
});
```

- [ ] **Step 3: Final check — run the app**

```bash
ddev launch
ddev php artisan route:list | head -40
```

Expected: All routes listed, app loads in browser.

- [ ] **Step 4: Commit**

```bash
git add resources/views/errors/ routes/web.php
git commit -m "feat: add error pages and homepage redirect — complete MVP"
```

---

## Verification Checklist

After completing all tasks, verify these flows work end-to-end:

### Guest access
- [ ] `/beatmaps` — public beatmap list loads
- [ ] `/login` — shows email + "Login with osu!" button
- [ ] `/dashboard` — redirects to login
- [ ] `/collections` — redirects to login

### osu! OAuth login
- [ ] Click "Login with osu!" → redirected to osu.ppy.sh
- [ ] After osu! approval → redirected to `/dashboard`
- [ ] User record created in `users` table with `osu_id` populated
- [ ] "Sync Scores" button appears on dashboard (user has osu_id)
- [ ] After sync: Almost FC table shows beatmaps with `miss_count > 0`

### Email/password login (seeded user)
- [ ] Login with `player@osu.test` / `password` → reaches dashboard
- [ ] Dashboard shows 0 syncs (no osu_id), sync button hidden

### Admin panel
- [ ] Login with `admin@osu.test` / `password`
- [ ] `/admin/users` shows all users
- [ ] Block a user → blocked user gets logged out on next request
- [ ] Unblock restores access

### Comments and collections
- [ ] Post a comment on `/beatmaps/{id}` — appears immediately
- [ ] Edit own comment — form pre-filled, update works
- [ ] Delete own comment — removed from page
- [ ] Create collection at `/collections/create`
- [ ] Add beatmap to collection from beatmap detail page
- [ ] Remove beatmap from collection detail page

### Tags
- [ ] Add `#dt #jumps` to a beatmap — tags appear
- [ ] Filter beatmap list by tag
