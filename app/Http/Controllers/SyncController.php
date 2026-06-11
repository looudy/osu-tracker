<?php

/**
 * SyncController — handles manual score synchronization triggered by the user clicking "Sync".
 * Calls OsuSyncService and redirects back with a status flash message.
 */

namespace App\Http\Controllers;

use App\Services\OsuSyncService;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(private OsuSyncService $syncService)
    {
    }

    /**
     * Manually trigger a score sync for the authenticated user.
     * Only available to users with an osu! account linked (osu_id must be set).
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function manualSync(Request $request)
    {
        $user = auth()->user();

        // Email-only users (no osu_id) cannot sync — they have no osu! account
        if (!$user->osu_id) {
            return back()->with('error', 'Score sync requires an osu! account. Please login with osu!');
        }

        $result = $this->syncService->syncUserScores($user);

        if (!empty($result['errors'])) {
            return back()->with('error', 'Sync failed: ' . implode(', ', $result['errors']));
        }

        return back()->with('success', "Synced {$result['new_scores']} new score(s).");
    }
}
