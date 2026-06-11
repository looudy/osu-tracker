<?php

/**
 * Tag model — a global tag that any user can attach to beatmaps.
 * Names are stored without the # symbol (e.g., "dt" not "#dt").
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /** Beatmaps that have this tag attached */
    public function beatmaps()
    {
        return $this->belongsToMany(Beatmap::class, 'beatmap_tag');
    }
}
