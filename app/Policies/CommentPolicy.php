<?php

/**
 * CommentPolicy — defines who can edit or delete a comment.
 * Both the comment owner and any admin have permission.
 */

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /**
     * Any user (including guests) can view comments — handled at the view level.
     *
     * @param  User|null  $user
     * @param  Comment  $comment
     * @return bool
     */
    public function view(?User $user, Comment $comment): bool
    {
        // Comments are publicly visible; no restriction here
        return true;
    }

    /**
     * Only the comment's author or an admin can edit it.
     *
     * @param  User  $user   The currently authenticated user
     * @param  Comment  $comment  The comment being edited
     * @return bool
     */
    public function update(User $user, Comment $comment): bool
    {
        // Allow if the user owns the comment or has admin privileges
        return $user->id === $comment->user_id || $user->isAdmin();
    }

    /**
     * Only the comment's author or an admin can delete it.
     *
     * @param  User  $user   The currently authenticated user
     * @param  Comment  $comment  The comment being deleted
     * @return bool
     */
    public function delete(User $user, Comment $comment): bool
    {
        // Same rule as update: owner or admin
        return $user->id === $comment->user_id || $user->isAdmin();
    }
}
