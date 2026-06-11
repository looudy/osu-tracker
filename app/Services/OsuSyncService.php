<?php

/**
 * OsuSyncService — all communication with the osu! API v2.
 * Fetches recent scores for a user and stores new ones in the database.
 * Beatmaps are created on the fly if they don't exist yet.
 */

namespace App\Services;

use App\Models\Beatmap;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OsuSyncService
{
    /** Base URL for all osu! API v2 requests */
    private const API_BASE = 'https://osu.ppy.sh/api/v2';

    /**
     * Fetch and store recent scores for a user.
     * Returns a summary with count of new scores and any errors.
     *
     * @param User $user  The user to sync
     * @param int  $limit Number of recent scores to fetch (max 100 per API)
     * @return array{new_scores: int, errors: string[]}
     */
    public function syncUserScores(User $user, int $limit = 50): array
    {
        // Guard: user must have a valid osu! access token
        if (!$user->osu_access_token) {
            return ['new_scores' => 0, 'errors' => ['No osu! access token. Please login again.']];
        }

        // Fetch recent scores from osu! API — include_fails=1 gets failed plays too
        $response = Http::withToken($user->osu_access_token)
            ->timeout(15)
            ->get(self::API_BASE . "/users/{$user->osu_id}/scores/recent", [
                'limit' => min($limit, 100), // API maximum is 100
                'include_fails' => 1,
            ]);

        // Handle API-level errors with specific messages for common cases
        if ($response->status() === 401) {
            // Token expired — user must re-authenticate via osu! OAuth
            Log::warning("osu! API 401 for user {$user->id} — token expired");
            return ['new_scores' => 0, 'errors' => ['Session expired. Please login with osu! again.']];
        }

        if ($response->status() === 429) {
            // Rate limited — too many requests in a short period
            Log::warning("osu! API 429 for user {$user->id} — rate limited");
            return ['new_scores' => 0, 'errors' => ['Too many requests. Wait a minute before syncing again.']];
        }

        if ($response->failed()) {
            Log::error("osu! API error {$response->status()} for user {$user->id}");
            return ['new_scores' => 0, 'errors' => ["API error: HTTP {$response->status()}"]];
        }

        $scores = $response->json();

        if (!is_array($scores)) {
            return ['new_scores' => 0, 'errors' => ['Unexpected API response format']];
        }

        $newCount = 0;
        $errors = [];

        foreach ($scores as $scoreData) {
            try {
                if ($this->storeScoreIfNew($user, $scoreData)) {
                    $newCount++;
                }
            } catch (\Exception $e) {
                $errors[] = "Failed to store score: {$e->getMessage()}";
                Log::error('Score storage failed', ['error' => $e->getMessage()]);
            }
        }

        // Update last sync timestamp so we know when data was last refreshed
        $user->update(['last_sync_at' => now()]);

        return ['new_scores' => $newCount, 'errors' => $errors];
    }

    /**
     * Store a single score if it hasn't been imported yet.
     * Also creates the beatmap record if it doesn't exist.
     *
     * @param User  $user      The user who set the score
     * @param array $scoreData Raw score object from osu! API
     * @return bool True if a new score was stored, false if it was a duplicate
     */
    private function storeScoreIfNew(User $user, array $scoreData): bool
    {
        // Beatmap data is nested inside the score response
        $beatmapData = $scoreData['beatmap'] ?? null;

        if (!$beatmapData) {
            return false; // Skip scores without beatmap data (shouldn't happen)
        }

        // beatmapset (title/artist) is a SIBLING of beatmap in the score object,
        // not nested inside it. The beatmap object only carries version/stars/etc.
        $beatmapsetData = $scoreData['beatmapset'] ?? $beatmapData['beatmapset'] ?? [];

        // Create or update the beatmap — beatmapset holds title/artist, beatmap holds version/stars
        $beatmap = Beatmap::updateOrCreate(
            ['beatmap_id' => $beatmapData['id']],
            [
                'title' => $beatmapsetData['title'] ?? $beatmapData['title'] ?? 'Unknown',
                'artist' => $beatmapsetData['artist'] ?? $beatmapData['artist'] ?? 'Unknown',
                'difficulty_name' => $beatmapData['version'] ?? 'Unknown',
                'stars' => $beatmapData['difficulty_rating'] ?? 0,
                'length_seconds' => $beatmapData['total_length'] ?? 0,
                'bpm' => $beatmapData['bpm'] ?? null,
                'circle_count' => $beatmapData['count_circles'] ?? null,
                'ar' => $beatmapData['ar'] ?? null,
                'od' => $beatmapData['accuracy'] ?? null, // "accuracy" key is OD in beatmap objects
            ]
        );

        // Deduplication check — same user + beatmap + time + score = same play
        $exists = Score::where('user_id', $user->id)
            ->where('beatmap_id', $beatmap->id)
            ->where('played_at', $scoreData['created_at'])
            ->where('score', $scoreData['score'])
            ->exists();

        if ($exists) {
            return false;
        }

        // Extract miss count — CRITICAL for the Almost FC leaderboard feature
        $missCount = $scoreData['statistics']['count_miss'] ?? 0;

        Score::create([
            'user_id' => $user->id,
            'beatmap_id' => $beatmap->id,
            'score' => $scoreData['score'],
            'accuracy' => round(($scoreData['accuracy'] ?? 0) * 100, 2), // API: 0-1 → store as 0-100
            'rank' => $scoreData['rank'] ?? 'D',
            'passed' => $scoreData['passed'] ?? false,
            'miss_count' => $missCount,
            'played_at' => $scoreData['created_at'],
        ]);

        return true;
    }
}
