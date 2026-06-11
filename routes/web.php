<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\OsuAuthController;
use App\Http\Controllers\BeatmapController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

/*
 * Homepage redirect — guests see the public beatmap list,
 * authenticated users land on their personal dashboard.
 * This avoids exposing the default Breeze welcome page.
 */
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('beatmaps.index');
});

/*
 * osu! OAuth routes — publicly accessible (no auth middleware).
 * These routes initiate and complete the OAuth2 login flow.
 *
 * /osu/redirect  → sends the user's browser to osu! to authorize our app
 * /osu/callback  → osu! redirects here after the user approves/denies access
 */
Route::get('/osu/redirect', [OsuAuthController::class, 'redirect'])->name('osu.redirect');
Route::get('/osu/callback', [OsuAuthController::class, 'callback'])->name('osu.callback');

/*
 * Public beatmap routes — no auth required.
 * Guests can browse and search the beatmap list; the show page shows
 * user-specific score data only when logged in.
 */
Route::get('/beatmaps', [BeatmapController::class, 'index'])->name('beatmaps.index');
Route::get('/beatmaps/{id}', [BeatmapController::class, 'show'])->name('beatmaps.show');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Manual score sync — osu! users only (osu_id required)
    Route::post('/sync-scores', [SyncController::class, 'manualSync'])->name('sync.scores');

    // Tag routes — attach is for all authenticated users; detach/destroy enforce admin check inside controller
    Route::post('/beatmaps/{beatmapId}/tags', [TagController::class, 'attach'])->name('tags.attach');
    Route::delete('/beatmaps/{beatmapId}/tags/{tagId}', [TagController::class, 'detach'])->name('tags.detach');
    Route::delete('/admin/tags/{tagId}', [TagController::class, 'destroy'])->name('tags.destroy');

    // Comment routes — all require authentication
    // store: any authenticated user may post a comment on a beatmap
    // edit/update/destroy: limited to comment owner or admin (enforced by CommentPolicy)
    Route::post('/beatmaps/{beatmapId}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::get('/comments/{id}/edit', [CommentController::class, 'edit'])->name('comments.edit');
    Route::put('/comments/{id}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{id}', [CommentController::class, 'destroy'])->name('comments.destroy');

});

/*
 * Admin routes — requires both 'auth' (must be logged in) and 'admin' (is_admin = true).
 * The 'admin' alias is registered in bootstrap/app.php pointing to AdminMiddleware.
 * All route names are prefixed with 'admin.' for easy reference in views.
 */
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // User management: list all users, block, and unblock
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::post('/users/{id}/block', [AdminController::class, 'block'])->name('block');
    Route::post('/users/{id}/unblock', [AdminController::class, 'unblock'])->name('unblock');

    // Content moderation: delete beatmaps, comments, and tags from the platform
    Route::delete('/beatmaps/{id}', [AdminController::class, 'deleteBeatmap'])->name('deleteBeatmap');
    Route::delete('/comments/{id}', [AdminController::class, 'deleteComment'])->name('deleteComment');
    Route::delete('/tags/{id}', [AdminController::class, 'deleteTag'])->name('deleteTag');
});

require __DIR__.'/auth.php';
