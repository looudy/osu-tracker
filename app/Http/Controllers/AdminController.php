<?php

/**
 * AdminController — user management and content moderation for platform admins.
 * All methods are protected by AdminMiddleware via the admin route group.
 *
 * Responsibilities:
 *  - List all users with pagination
 *  - Block / unblock users (sets is_blocked flag and records blocked_at timestamp)
 *  - Force-delete beatmaps, comments, and tags (admin moderation)
 */

namespace App\Http\Controllers;

use App\Models\Beatmap;
use App\Models\Comment;
use App\Models\Tag;
use App\Models\User;

class AdminController extends Controller
{
    /**
     * Display a paginated list of all registered users for the admin panel.
     *
     * Ordered newest-first so newly registered accounts are immediately visible
     * at the top without having to scroll through the entire list.
     *
     * @return \Illuminate\View\View
     */
    public function users()
    {
        // Latest first so new registrations appear at the top
        $users = User::orderBy('created_at', 'desc')->paginate(20);

        return view('admin.users', compact('users'));
    }

    /**
     * Block a user — prevents them from logging in via any method.
     * Sets is_blocked=true and records the exact timestamp of the block action.
     * The CheckBlocked middleware will force-logout the user on their next request.
     *
     * @param  int  $userId  The ID of the user to block
     * @return \Illuminate\Http\RedirectResponse
     */
    public function block(int $userId)
    {
        $user = User::findOrFail($userId);

        // Prevent admins from accidentally blocking themselves — would lock out the admin panel
        abort_if($user->id === auth()->id(), 400, 'You cannot block yourself.');

        $user->update([
            'is_blocked' => true,
            'blocked_at' => now(),
        ]);

        return back()->with('success', "User \"{$user->name}\" has been blocked.");
    }

    /**
     * Unblock a user — restores their ability to log in.
     * Clears the is_blocked flag and nullifies blocked_at.
     *
     * @param  int  $userId  The ID of the user to unblock
     * @return \Illuminate\Http\RedirectResponse
     */
    public function unblock(int $userId)
    {
        $user = User::findOrFail($userId);
        $user->update([
            'is_blocked' => false,
            'blocked_at' => null,
        ]);

        return back()->with('success', "User \"{$user->name}\" has been unblocked.");
    }

    /**
     * Force delete a beatmap — cascades to scores, comments, and tag pivots.
     * Database foreign key constraints with onDelete cascade handle cleanup automatically.
     *
     * @param  int  $beatmapId  The ID of the beatmap to delete
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteBeatmap(int $beatmapId)
    {
        Beatmap::findOrFail($beatmapId)->delete();

        return back()->with('success', 'Beatmap deleted.');
    }

    /**
     * Delete any comment — admin moderation power bypasses ownership checks.
     * Uses the same underlying delete as the user-facing destroy (policy does not apply here).
     *
     * @param  int  $commentId  The ID of the comment to delete
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteComment(int $commentId)
    {
        Comment::findOrFail($commentId)->delete();

        return back()->with('success', 'Comment deleted.');
    }

    /**
     * Delete a global tag and remove it from all beatmaps via cascade.
     * The beatmap_tag pivot rows are removed automatically by the DB constraint.
     *
     * @param  int  $tagId  The ID of the tag to delete
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteTag(int $tagId)
    {
        Tag::findOrFail($tagId)->delete();

        return back()->with('success', 'Tag deleted.');
    }
}
