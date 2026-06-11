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
