<?php

/**
 * CollectionController — manage the authenticated user's beatmap collections.
 * Each collection is private to its owner. Only the owner can modify it.
 * Ownership is enforced by scoping all queries with where('user_id', auth()->id()).
 */

namespace App\Http\Controllers;

use App\Models\Beatmap;
use App\Models\Collection;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    /**
     * List all of the authenticated user's collections with beatmap counts.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Scope to the current user and eager-count beatmaps to avoid N+1
        $collections = Collection::where('user_id', auth()->id())
            ->withCount('beatmaps')
            ->orderBy('name')
            ->get();

        return view('collections.index', compact('collections'));
    }

    /**
     * Show the form to create a new collection.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('collections.create');
    }

    /**
     * Store a newly created collection in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // Validate name (required) and optional description
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // Create the collection and automatically assign it to the current user
        Collection::create([
            'user_id'     => auth()->id(),
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('collections.index')
            ->with('success', 'Collection created.');
    }

    /**
     * Show the form to edit an existing collection (owner only).
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function edit(int $id)
    {
        // Scoping with user_id ensures only the owner can access — returns 404 for others
        $collection = Collection::where('user_id', auth()->id())->findOrFail($id);

        return view('collections.edit', compact('collection'));
    }

    /**
     * Update a collection's name and description.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $id)
    {
        // Only the owner can update — findOrFail returns 404 if not owner
        $collection = Collection::where('user_id', auth()->id())->findOrFail($id);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $collection->update($validated);

        return redirect()->route('collections.index')
            ->with('success', 'Collection updated.');
    }

    /**
     * Delete a collection and its beatmap associations.
     * Cascade is handled automatically by the belongsToMany pivot table.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id)
    {
        $collection = Collection::where('user_id', auth()->id())->findOrFail($id);
        // Deleting the collection also detaches beatmaps via DB cascade on the pivot
        $collection->delete();

        return redirect()->route('collections.index')
            ->with('success', 'Collection deleted.');
    }

    /**
     * Show all beatmaps in a specific collection.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show(int $id)
    {
        // Eager-load beatmaps and their tags so the view has no N+1 queries
        $collection = Collection::where('user_id', auth()->id())
            ->with('beatmaps.tags')
            ->findOrFail($id);

        return view('collections.show', compact('collection'));
    }

    /**
     * Add a beatmap to a collection (without removing existing beatmaps).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $collectionId
     * @param  int  $beatmapId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addBeatmap(Request $request, int $collectionId, int $beatmapId)
    {
        // Verify ownership of the collection
        $collection = Collection::where('user_id', auth()->id())->findOrFail($collectionId);

        // Verify the beatmap exists before attaching
        $beatmap = Beatmap::findOrFail($beatmapId);

        // syncWithoutDetaching adds without removing other existing beatmaps in the collection
        $collection->beatmaps()->syncWithoutDetaching([$beatmap->id]);

        return back()->with('success', "Added to \"{$collection->name}\".");
    }

    /**
     * Remove a beatmap from a collection (detach only, does not delete the beatmap).
     *
     * @param  int  $collectionId
     * @param  int  $beatmapId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeBeatmap(int $collectionId, int $beatmapId)
    {
        // Verify ownership before allowing removal
        $collection = Collection::where('user_id', auth()->id())->findOrFail($collectionId);

        // detach removes only the pivot record, not the beatmap itself
        $collection->beatmaps()->detach($beatmapId);

        return back()->with('success', 'Removed from collection.');
    }
}
