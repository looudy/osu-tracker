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
