<?php

/**
 * TagController — global tag management for beatmaps.
 * Any authenticated user can add tags to any beatmap.
 * Only admins can remove tags or delete global tags.
 */

namespace App\Http\Controllers;

use App\Models\Beatmap;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Parse a string like "#dt #jumps #technical" and attach tags to a beatmap.
     * Creates tags that don't exist yet (global tag pool shared by all users).
     *
     * @param  Request  $request
     * @param  int  $beatmapId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function attach(Request $request, int $beatmapId)
    {
        $request->validate([
            'tags_input' => ['required', 'string', 'max:200'],
        ]);

        $beatmap = Beatmap::findOrFail($beatmapId);

        // Extract tag names from "#tagname" format using regex
        // Example: "#dt #jumps" → ['dt', 'jumps']
        preg_match_all('/#(\w+)/', $request->tags_input, $matches);
        $tagNames = array_unique($matches[1]);

        if (empty($tagNames)) {
            return back()->with('error', 'No valid tags found. Use format: #tagname');
        }

        // Find or create each tag, collect their IDs for the pivot sync
        $tagIds = [];
        foreach ($tagNames as $name) {
            $tag = Tag::firstOrCreate(['name' => strtolower($name)]);
            $tagIds[] = $tag->id;
        }

        // syncWithoutDetaching adds new tags without removing existing ones
        $beatmap->tags()->syncWithoutDetaching($tagIds);

        return back()->with('success', 'Tags added successfully.');
    }

    /**
     * Remove a single tag from a beatmap (admin only).
     * The global tag still exists — it's just detached from this beatmap.
     *
     * @param  int  $beatmapId
     * @param  int  $tagId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function detach(int $beatmapId, int $tagId)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Admin access required.');

        $beatmap = Beatmap::findOrFail($beatmapId);
        $beatmap->tags()->detach($tagId);

        return back()->with('success', 'Tag removed from beatmap.');
    }

    /**
     * Delete a global tag entirely — removes it from all beatmaps (admin only).
     * The beatmap_tag pivot rows are cascade-deleted by the DB foreign key.
     *
     * @param  int  $tagId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $tagId)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Admin access required.');

        Tag::findOrFail($tagId)->delete();

        return back()->with('success', 'Tag deleted globally.');
    }
}
