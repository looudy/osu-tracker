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
