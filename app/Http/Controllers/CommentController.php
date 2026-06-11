<?php

/**
 * CommentController — CRUD for beatmap comments.
 * store/edit/update/destroy — owners and admins can edit/delete via CommentPolicy.
 */

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Store a new comment on a beatmap.
     * Any authenticated user can comment.
     *
     * @param  Request  $request
     * @param  int  $beatmapId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, int $beatmapId)
    {
        // Validate: content must be present, a string, 1–1000 chars
        $validated = $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        // Create the comment linked to the current user and the beatmap
        Comment::create([
            'user_id'    => auth()->id(),
            'beatmap_id' => $beatmapId,
            'content'    => $validated['content'],
        ]);

        return redirect()->route('beatmaps.show', $beatmapId)
            ->with('success', 'Comment posted.');
    }

    /**
     * Show the edit form for an existing comment.
     * Only the comment owner or an admin may access this (enforced by CommentPolicy).
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function edit(int $id)
    {
        // Fetch the comment or return 404 if it doesn't exist
        $comment = Comment::findOrFail($id);

        // Authorization: CommentPolicy::update() — owner or admin only
        $this->authorize('update', $comment);

        return view('comments.edit', compact('comment'));
    }

    /**
     * Update a comment's content.
     * Authorization is checked via CommentPolicy before saving.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $id)
    {
        $comment = Comment::findOrFail($id);

        // Authorization: CommentPolicy::update() — owner or admin only
        $this->authorize('update', $comment);

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        // Persist the updated content
        $comment->update(['content' => $validated['content']]);

        return redirect()->route('beatmaps.show', $comment->beatmap_id)
            ->with('success', 'Comment updated.');
    }

    /**
     * Delete a comment.
     * Authorization is checked via CommentPolicy — owner or admin.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id)
    {
        $comment = Comment::findOrFail($id);

        // Authorization: CommentPolicy::delete() — owner or admin only
        $this->authorize('delete', $comment);

        // Save beatmap_id before deleting so we can redirect back to it
        $beatmapId = $comment->beatmap_id;
        $comment->delete();

        return redirect()->route('beatmaps.show', $beatmapId)
            ->with('success', 'Comment deleted.');
    }
}
