<?php

/**
 * BeatmapController — public beatmap list with search/filter and per-beatmap detail page.
 * The index is public (no auth required). The show page shows user-specific data if logged in.
 */

namespace App\Http\Controllers;

use App\Models\Beatmap;
use App\Models\Tag;
use Illuminate\Http\Request;

class BeatmapController extends Controller
{
    /**
     * Display paginated list of all beatmaps with optional search and filter.
     * GET /beatmaps?search=...&tag_id=...
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Start with a base query — we'll add conditions as filters are provided
        $query = Beatmap::query();

        // Text search: match title or artist (case-insensitive via LIKE)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('artist', 'LIKE', "%{$search}%");
            });
        }

        // Filter by global tag — uses whereHas to avoid cartesian product from join
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', fn($q) => $q->where('tags.id', $request->tag_id));
        }

        // withQueryString keeps search params in pagination links so they persist across pages
        $beatmaps = $query->orderBy('title')->paginate(20)->withQueryString();

        // All tags for the tag dropdown filter
        $tags = Tag::orderBy('name')->get();

        return view('beatmaps.index', compact('beatmaps', 'tags'));
    }

    /**
     * Show a single beatmap with metadata, the current user's scores, comments, and tags.
     *
     * @param int $id  Internal beatmap primary key (not osu! beatmap_id)
     * @return \Illuminate\View\View
     */
    public function show(int $id)
    {
        // Eager-load tags and comments with their authors to avoid N+1 queries
        $beatmap = Beatmap::with(['tags', 'comments.user'])->findOrFail($id);

        // Only show the current user's own scores (scores are private per-user)
        $userScores = auth()->check()
            ? $beatmap->scores()->where('user_id', auth()->id())->orderBy('played_at', 'desc')->get()
            : collect();

        return view('beatmaps.show', compact('beatmap', 'userScores'));
    }
}
