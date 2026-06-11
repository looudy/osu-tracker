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
