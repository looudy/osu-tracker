# Simplify Project Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove Collections, strip email/password auth (keep only osu! OAuth), replace Tailwind with Bootstrap 5, inline all Blade components, and clean up routes/controllers.

**Architecture:** Keep /dashboard, /beatmaps, /profile, /admin fully working. All views extend `layouts.app` with Bootstrap 5 CDN. No Blade components, no Alpine.js, no Tailwind. CSS file `public/css/style.css` stays but is trimmed of collection/Tailwind-specific rules.

**Tech Stack:** Laravel 13, Bootstrap 5 CDN, plain PHP Blade templates, existing `public/css/style.css` for custom osu! theme colours.

---

## Task 1: Delete Collections — routes, controller, model, views, migrations

**Files:**
- Delete: `app/Http/Controllers/CollectionController.php`
- Delete: `app/Models/Collection.php`
- Delete: `resources/views/collections/` (entire directory)
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/BeatmapController.php`
- Modify: `resources/views/beatmaps/index.blade.php`
- Modify: `resources/views/beatmaps/show.blade.php`

- [ ] **Step 1: Remove collection routes from web.php**

Replace the collection route block in `routes/web.php`. Remove these lines (inside the `auth` middleware group):

```php
// DELETE these lines:
Route::resource('collections', CollectionController::class)->except(['show']);
Route::get('/collections/{id}/beatmaps', [CollectionController::class, 'show'])->name('collections.show');
Route::post('/collections/{collectionId}/beatmaps/{beatmapId}', [CollectionController::class, 'addBeatmap'])->name('collections.addBeatmap');
Route::delete('/collections/{collectionId}/beatmaps/{beatmapId}', [CollectionController::class, 'removeBeatmap'])->name('collections.removeBeatmap');
```

Also remove the `use App\Http\Controllers\CollectionController;` import at the top.

- [ ] **Step 2: Remove collection filter from BeatmapController::index**

In `app/Http/Controllers/BeatmapController.php`, remove the collection filter block and the `$collections` variable:

```php
// REMOVE this block:
if ($request->filled('collection_id') && auth()->check()) {
    $query->whereHas('collections', fn($q) => $q->where('collections.id', $request->collection_id));
}

// REMOVE this line:
$collections = auth()->check()
    ? Collection::where('user_id', auth()->id())->orderBy('name')->get()
    : collect();
```

Change the return to:
```php
return view('beatmaps.index', compact('beatmaps', 'tags'));
```

Remove the `use App\Models\Collection;` import at the top.

- [ ] **Step 3: Remove collection filter dropdown from beatmaps/index.blade.php**

In `resources/views/beatmaps/index.blade.php` remove:
```blade
{{-- Collection filter — only shown to authenticated users --}}
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
```

- [ ] **Step 4: Remove "Add to Collection" block from beatmaps/show.blade.php**

In `resources/views/beatmaps/show.blade.php` remove the entire block:
```blade
{{-- Add to collection dropdown — only shown when the user has at least one collection --}}
@auth
@if($userCollections->isNotEmpty())
<div class="card">
    <h2>Add to Collection</h2>
    <form action="{{ route('collections.addBeatmap', [$userCollections->first()->id, $beatmap->id]) }}"
          method="POST" class="inline-form" id="add-to-collection-form">
        @csrf
        <select name="collection_id" class="form-input"
                onchange="this.form.action='{{ url('/collections') }}/'+this.value+'/beatmaps/{{ $beatmap->id }}'">
            @foreach($userCollections as $col)
                <option value="{{ $col->id }}">{{ $col->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-secondary">Add</button>
    </form>
</div>
@endif
@endauth
```

Also update `BeatmapController::show` — remove `$userCollections` variable and compact:
```php
// REMOVE:
$userCollections = auth()->check()
    ? Collection::where('user_id', auth()->id())->get()
    : collect();

// Change return to:
return view('beatmaps.show', compact('beatmap', 'userScores'));
```

- [ ] **Step 5: Delete files**

```bash
rm app/Http/Controllers/CollectionController.php
rm app/Models/Collection.php
rm -rf resources/views/collections
```

- [ ] **Step 6: Run the app and verify /beatmaps loads without errors**

```bash
php artisan route:list | grep collection
```
Expected: no output (no collection routes).

```bash
php artisan view:clear && php artisan config:clear
```

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "remove: collections feature (controller, model, views, routes)"
```

---

## Task 2: Delete email/password auth — keep only osu! OAuth

**Files:**
- Delete: `app/Http/Controllers/Auth/RegisteredUserController.php`
- Delete: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Delete: `app/Http/Controllers/Auth/PasswordController.php`
- Delete: `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- Delete: `app/Http/Controllers/Auth/NewPasswordController.php`
- Delete: `app/Http/Controllers/Auth/ConfirmablePasswordController.php`
- Delete: `app/Http/Controllers/Auth/EmailVerificationPromptController.php`
- Delete: `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`
- Delete: `app/Http/Controllers/Auth/VerifyEmailController.php`
- Delete: `resources/views/auth/register.blade.php`
- Delete: `resources/views/auth/forgot-password.blade.php`
- Delete: `resources/views/auth/reset-password.blade.php`
- Delete: `resources/views/auth/verify-email.blade.php`
- Delete: `resources/views/auth/confirm-password.blade.php`
- Delete: `resources/views/layouts/guest.blade.php`
- Rewrite: `routes/auth.php`
- Modify: `routes/web.php` (remove `verified` middleware from dashboard route)
- Rewrite: `resources/views/auth/login.blade.php`

- [ ] **Step 1: Rewrite routes/auth.php to only have logout**

Replace the entire content of `routes/auth.php` with:

```php
<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
```

Wait — we are deleting `AuthenticatedSessionController`. The logout route needs to stay but be handled differently. We'll keep a minimal `AuthenticatedSessionController` for logout only.

- [ ] **Step 2: Create minimal AuthenticatedSessionController (logout only)**

Replace `app/Http/Controllers/Auth/AuthenticatedSessionController.php` with:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
```

- [ ] **Step 3: Rewrite routes/auth.php**

```php
<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('login', function () {
    return view('auth.login');
})->middleware('guest')->name('login');

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
```

- [ ] **Step 4: Remove `verified` middleware from dashboard route in web.php**

In `routes/web.php` change:
```php
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
```
To:
```php
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');
```

- [ ] **Step 5: Delete unused auth controllers and views**

```bash
rm app/Http/Controllers/Auth/RegisteredUserController.php
rm app/Http/Controllers/Auth/PasswordController.php
rm app/Http/Controllers/Auth/PasswordResetLinkController.php
rm app/Http/Controllers/Auth/NewPasswordController.php
rm app/Http/Controllers/Auth/ConfirmablePasswordController.php
rm app/Http/Controllers/Auth/EmailVerificationPromptController.php
rm app/Http/Controllers/Auth/EmailVerificationNotificationController.php
rm app/Http/Controllers/Auth/VerifyEmailController.php
rm resources/views/auth/register.blade.php
rm resources/views/auth/forgot-password.blade.php
rm resources/views/auth/reset-password.blade.php
rm resources/views/auth/verify-email.blade.php
rm resources/views/auth/confirm-password.blade.php
rm resources/views/layouts/guest.blade.php
```

- [ ] **Step 6: Rewrite login.blade.php — osu! OAuth only, no email form**

Replace `resources/views/auth/login.blade.php` with:

```blade
@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="container">
    <div class="card" style="max-width:420px; margin:3rem auto;">
        <h1 style="margin-bottom:1.5rem;">Login</h1>

        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <a href="{{ route('osu.redirect') }}" class="btn btn-primary" style="display:block; text-align:center; font-size:1.1rem; padding:0.8rem;">
            Login with osu!
        </a>
    </div>
</div>
@endsection
```

- [ ] **Step 7: Verify login page loads and routes work**

```bash
php artisan route:list | grep -E "login|logout|register|password|verify"
```
Expected output — only these routes remain:
```
GET   login    (closure)
POST  logout   AuthenticatedSessionController@destroy
GET   osu/redirect ...
GET   osu/callback  ...
```

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "remove: email/password auth, keep only osu! OAuth login"
```

---

## Task 3: Replace Tailwind with Bootstrap 5 in layouts and navigation

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Rewrite: `resources/views/layouts/navigation.blade.php`
- Modify: `package.json`
- Delete: `tailwind.config.js` (if exists)
- Delete: `postcss.config.js` (if exists)
- Modify: `vite.config.js` (if it imports Tailwind)

- [ ] **Step 1: Add Bootstrap 5 CDN to layouts/app.blade.php and remove Vite CSS**

Replace the `<head>` section of `resources/views/layouts/app.blade.php`. The current file already uses `public/css/style.css` and has no Vite/Tailwind. Just add Bootstrap CDN before the existing stylesheet:

```blade
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'osu! Tracker') — osu! Personal Tracker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
```

Also add Bootstrap JS bundle before `</body>`:
```blade
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
```
Place it just before the existing sync button script.

- [ ] **Step 2: Rewrite navigation.blade.php — replace Alpine.js/Tailwind with Bootstrap navbar**

Replace entire `resources/views/layouts/navigation.blade.php` with:

```blade
<nav class="navbar navbar-expand-md" style="background:#1a1a2e; padding:0 1.5rem; height:56px;">
    <a class="navbar-brand" href="{{ route('beatmaps.index') }}" style="color:#e94c6c; font-weight:bold; font-size:1.3rem;">osu! Tracker</a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" style="border-color:#555;">
        <span class="navbar-toggler-icon" style="filter:invert(1);"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link" href="{{ route('beatmaps.index') }}" style="color:#ddd;">Beatmaps</a></li>
            @auth
            <li class="nav-item"><a class="nav-link" href="{{ route('dashboard') }}" style="color:#ddd;">Dashboard</a></li>
            @if(auth()->user()->isAdmin())
            <li class="nav-item"><a class="nav-link" href="{{ route('admin.users') }}" style="color:#f0ad4e;">Admin</a></li>
            @endif
            @endauth
        </ul>

        <ul class="navbar-nav ms-auto">
            @auth
            <li class="nav-item"><a class="nav-link" href="{{ route('profile.edit') }}" style="color:#ddd;">{{ auth()->user()->osu_username ?? auth()->user()->name }}</a></li>
            <li class="nav-item">
                <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-sm" style="color:#ddd; border:1px solid #555; margin-top:10px;">Logout</button>
                </form>
            </li>
            @else
            <li class="nav-item"><a class="nav-link" href="{{ route('login') }}" style="color:#ddd;">Login</a></li>
            @endauth
        </ul>
    </div>
</nav>
```

- [ ] **Step 3: Update layouts/app.blade.php to use navigation.blade.php include**

The current `layouts/app.blade.php` has the navbar inline — not including `navigation.blade.php`. Confirm this. The nav is already inline in `app.blade.php`. The `navigation.blade.php` file is the old Breeze Tailwind nav that is NOT included anywhere. We can simply delete it.

```bash
rm resources/views/layouts/navigation.blade.php
```

- [ ] **Step 4: Remove Tailwind from package.json**

Replace `package.json` with:
```json
{
    "$schema": "https://www.schemastore.org/package.json",
    "private": true,
    "type": "module",
    "scripts": {
        "build": "vite build",
        "dev": "vite"
    },
    "devDependencies": {
        "laravel-vite-plugin": "^3.1",
        "vite": "^8.0.0"
    }
}
```

- [ ] **Step 5: Delete Tailwind config files if they exist**

```bash
ls /Users/kirillpoznak/osu-personal-tracker/tailwind.config.js 2>/dev/null && rm /Users/kirillpoznak/osu-personal-tracker/tailwind.config.js || echo "not found"
ls /Users/kirillpoznak/osu-personal-tracker/postcss.config.js 2>/dev/null && rm /Users/kirillpoznak/osu-personal-tracker/postcss.config.js || echo "not found"
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "replace: Tailwind with Bootstrap 5 CDN in layout and navigation"
```

---

## Task 4: Inline all Blade components — delete /components directory

The profile views and their partials still use `<x-app-layout>`, `<x-primary-button>`, `<x-text-input>`, etc. We rewrite them with plain HTML.

**Files:**
- Rewrite: `resources/views/profile/edit.blade.php`
- Rewrite: `resources/views/profile/partials/update-profile-information-form.blade.php`
- Rewrite: `resources/views/profile/partials/update-password-form.blade.php`
- Rewrite: `resources/views/profile/partials/delete-user-form.blade.php`
- Rewrite: `resources/views/comments/edit.blade.php`
- Delete: `resources/views/components/` (entire directory)

- [ ] **Step 1: Read current comments/edit.blade.php**

```bash
cat resources/views/comments/edit.blade.php
```

- [ ] **Step 2: Rewrite profile/edit.blade.php**

Replace entire file with:

```blade
@extends('layouts.app')

@section('title', 'Profile')

@section('content')
<div class="container" style="max-width:700px;">
    <h1 style="margin-bottom:1.5rem;">Profile Settings</h1>

    @if(session('status') === 'profile-updated')
        <div class="alert alert-success">Profile updated.</div>
    @endif

    <div class="card">
        @include('profile.partials.update-profile-information-form')
    </div>

    <div class="card">
        @include('profile.partials.update-password-form')
    </div>

    <div class="card">
        @include('profile.partials.delete-user-form')
    </div>
</div>
@endsection
```

- [ ] **Step 3: Rewrite profile/partials/update-profile-information-form.blade.php**

```blade
<h2>Profile Information</h2>
<p class="text-muted" style="margin-bottom:1rem;">Update your name and email address.</p>

<form method="POST" action="{{ route('profile.update') }}">
    @csrf
    @method('PATCH')

    <div class="form-group">
        <label for="name">Name</label>
        <input id="name" name="name" type="text" class="form-input" value="{{ old('name', $user->name) }}" required>
        @error('name')<div class="form-error">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" class="form-input" value="{{ old('email', $user->email) }}" required>
        @error('email')<div class="form-error">{{ $message }}</div>@enderror
    </div>

    <button type="submit" class="btn btn-primary">Save</button>
</form>
```

- [ ] **Step 4: Rewrite profile/partials/update-password-form.blade.php**

```blade
<h2>Update Password</h2>
<p class="text-muted" style="margin-bottom:1rem;">Use a long, random password to stay secure.</p>

<form method="POST" action="{{ route('password.update') }}">
    @csrf
    @method('PUT')

    <div class="form-group">
        <label for="current_password">Current Password</label>
        <input id="current_password" name="current_password" type="password" class="form-input">
        @error('current_password', 'updatePassword')<div class="form-error">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label for="password">New Password</label>
        <input id="password" name="password" type="password" class="form-input">
        @error('password', 'updatePassword')<div class="form-error">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label for="password_confirmation">Confirm Password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" class="form-input">
    </div>

    <button type="submit" class="btn btn-primary">Save</button>

    @if(session('status') === 'password-updated')
        <span class="text-muted" style="margin-left:1rem;">Saved.</span>
    @endif
</form>
```

**Note:** We keep the password update form here since the profile route is still available. The password.update route is in the auth group — if it was deleted in Task 2, remove this partial entirely and exclude it from profile/edit.blade.php. Double-check `routes/auth.php` — the `PUT /password` route was removed in Task 2 since we deleted `PasswordController`. So this form will 404 on submit.

To fix: either remove the password form from profile, or add back just the `PUT /password` route with a minimal inline controller. The simpler choice is to remove the password section from profile since users log in via osu! OAuth.

**Revised Step 4: Remove password partial from profile/edit.blade.php**

In `resources/views/profile/edit.blade.php`, remove the block:
```blade
    <div class="card">
        @include('profile.partials.update-password-form')
    </div>
```

Delete the file too:
```bash
rm resources/views/profile/partials/update-password-form.blade.php
```

- [ ] **Step 5: Rewrite profile/partials/delete-user-form.blade.php**

Replace with plain HTML confirmation (no Alpine.js modal):

```blade
<h2>Delete Account</h2>
<p class="text-muted" style="margin-bottom:1rem;">Once deleted, all your data will be permanently removed.</p>

<form method="POST" action="{{ route('profile.destroy') }}"
      onsubmit="return confirm('Are you sure you want to delete your account? This cannot be undone.')">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-danger">Delete Account</button>
</form>
```

- [ ] **Step 6: Read and rewrite comments/edit.blade.php**

First read it:
```bash
cat resources/views/comments/edit.blade.php
```

Then rewrite replacing any `<x-*>` components with plain HTML using the same pattern as other pages (extends layouts.app, use .form-input, .btn, etc.):

```blade
@extends('layouts.app')

@section('title', 'Edit Comment')

@section('content')
<div class="container" style="max-width:600px;">
    <h1>Edit Comment</h1>

    <div class="card" style="margin-top:1.5rem;">
        <form method="POST" action="{{ route('comments.update', $comment->id) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="content">Comment</label>
                <textarea id="content" name="content" class="form-input" rows="4" required>{{ old('content', $comment->content) }}</textarea>
                @error('content')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div style="display:flex; gap:0.75rem;">
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('beatmaps.show', $comment->beatmap_id) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
```

- [ ] **Step 7: Delete all Blade components**

```bash
rm -rf resources/views/components
```

- [ ] **Step 8: Verify no x- components remain**

```bash
grep -r "x-app-layout\|x-guest-layout\|x-primary-button\|x-secondary-button\|x-danger-button\|x-text-input\|x-input-label\|x-input-error\|x-dropdown\|x-modal\|x-nav-link\|x-responsive-nav-link\|x-application-logo\|x-auth-session-status" resources/views/
```
Expected: no output.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "remove: Blade components, rewrite profile/comments views with plain HTML"
```

---

## Task 5: Update layouts/app.blade.php navbar — remove Collections link, clean up

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Remove Collections nav link and Register link from app.blade.php**

In the navbar section of `resources/views/layouts/app.blade.php`, remove:
```blade
<li><a href="{{ route('collections.index') }}">Collections</a></li>
```

Also remove:
```blade
<li><a href="{{ route('register') }}">Register</a></li>
```

The nav should now only show: Beatmaps | Dashboard (auth) | Admin (admin) | Logout / Login.

- [ ] **Step 2: Verify the nav renders correctly by checking for broken routes**

```bash
php artisan route:list --name=collections
```
Expected: no output.

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "remove: collections and register links from navbar"
```

---

## Task 6: Clean up welcome.blade.php and CSS

**Files:**
- Modify: `resources/views/welcome.blade.php`
- Modify: `public/css/style.css`

- [ ] **Step 1: Replace welcome.blade.php with a simple redirect**

The `/` route in `web.php` already does the redirect logic. `welcome.blade.php` is never rendered. Replace it with a minimal stub:

```blade
{{-- Not rendered — / route redirects based on auth state --}}
@extends('layouts.app')
@section('content')
<div class="container"><p>Redirecting...</p></div>
@endsection
```

- [ ] **Step 2: Remove collection CSS rules from style.css**

In `public/css/style.css`, remove the `/* ---- Collections list ---- */` block:

```css
/* REMOVE this entire block: */
/* ---- Collections list ---- */
.collection-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 0.75rem 0; border-bottom: 1px solid #e0e0e0; }
.collection-item:last-child { border-bottom: none; }
.collection-item .actions { display: flex; gap: 0.5rem; }
```

- [ ] **Step 3: Remove one-miss special color from CSS (simplify as requested)**

In `public/css/style.css`, remove:
```css
.one-miss { color: #e94c6c; font-weight: bold; }
```

Also in `resources/views/dashboard.blade.php`, remove the `one-miss` class logic from the miss count cell:
```blade
{{-- Change from: --}}
<td class="miss-count {{ $map->best_miss_count == 1 ? 'one-miss' : '' }}">

{{-- Change to: --}}
<td class="miss-count">
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "simplify: welcome page stub, remove collection CSS, remove one-miss color logic"
```

---

## Task 7: Final verification

- [ ] **Step 1: Check no broken imports in PHP files**

```bash
php artisan route:list
```
Expected: no errors, all routes listed cleanly.

- [ ] **Step 2: Check no leftover references to deleted things**

```bash
grep -r "CollectionController\|collections\.index\|collections\.create\|RegisteredUserController\|PasswordResetLinkController\|NewPasswordController\|ConfirmablePasswordController\|EmailVerification\|VerifyEmailController\|x-app-layout\|x-guest-layout" app/ routes/ resources/views/ --include="*.php" --include="*.blade.php"
```
Expected: no output.

- [ ] **Step 3: Check for any remaining Tailwind classes in blade files**

```bash
grep -r "class=\".*\(bg-\|text-gray\|sm:px\|max-w-7xl\|font-semibold\|space-y\|py-12\)" resources/views/ --include="*.blade.php"
```
Expected: no output (or only inside inline style="" strings which is fine).

- [ ] **Step 4: Confirm deleted directories are gone**

```bash
ls resources/views/collections/ 2>/dev/null && echo "EXISTS - ERROR" || echo "OK - deleted"
ls resources/views/components/ 2>/dev/null && echo "EXISTS - ERROR" || echo "OK - deleted"
```
Expected: both show "OK - deleted".

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "chore: final cleanup and verification pass"
```
