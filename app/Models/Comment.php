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
