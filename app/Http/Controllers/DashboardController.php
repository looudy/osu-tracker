<?php

/**
 * DashboardController — main page for authenticated users.
 * Shows personal stats, the Almost FC leaderboard, and recent scores.
 */

namespace App\Http\Controllers;

use App\Models\Score;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the user's personal dashboard.
     * Computes aggregated stats and the Almost FC beatmap list using raw SQL for performance.
     */
    public function index()
    {
        $user = auth()->user();

        // Aggregated stats from all imported scores for this user
        $totalPlays = Score::forUser($user->id)->count();
        $totalUniqueMaps = Score::forUser($user->id)->distinct('beatmap_id')->count('beatmap_id');
        $bestAccuracy = Score::forUser($user->id)->max('accuracy') ?? 0;
        $passedCount = Score::forUser($user->id)->where('passed', true)->count();
        $passRate = $totalPlays > 0 ? round(($passedCount / $totalPlays) * 100, 1) : 0;

        /**
         * Almost FC query — finds beatmaps where the user has at least one score with misses.
         * Groups by beatmap and shows the BEST attempt (fewest misses, then highest accuracy).
         * Ordered by fewest misses first — top entries are closest to Full Combo.
         * Uses raw SQL for the correlated subquery needed for best_score.
         */
        $almostFcMaps = DB::select("
            SELECT
                b.id,
                b.title,
                b.artist,
                b.difficulty_name,
                b.stars,
                b.beatmap_id AS osu_beatmap_id,
                MIN(s.miss_count) AS best_miss_count,
                MAX(s.accuracy) AS best_accuracy,
                (
                    SELECT s2.score
                    FROM scores s2
                    WHERE s2.beatmap_id = b.id AND s2.user_id = ?
                    ORDER BY s2.miss_count ASC, s2.accuracy DESC
                    LIMIT 1
                ) AS best_score
            FROM scores s
            JOIN beatmaps b ON b.id = s.beatmap_id
            WHERE s.user_id = ? AND s.miss_count > 0
            GROUP BY s.beatmap_id, b.id, b.title, b.artist, b.difficulty_name, b.stars, b.beatmap_id
            ORDER BY best_miss_count ASC
            LIMIT 20
        ", [$user->id, $user->id]);

        // 10 most recent scores for the "Recent Activity" section at the bottom
        $recentScores = Score::forUser($user->id)
            ->with('beatmap')
            ->orderBy('played_at', 'desc')
            ->limit(10)
            ->get();

        return view('dashboard', compact(
            'user',
            'totalPlays',
            'totalUniqueMaps',
            'bestAccuracy',
            'passRate',
            'almostFcMaps',
            'recentScores'
        ));
    }
}
