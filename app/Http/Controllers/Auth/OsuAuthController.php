<?php

/**
 * OsuAuthController — Handles the two-leg osu! OAuth2 login flow.
 *
 * Leg 1 (redirect): user clicks "Login with osu!" → we redirect them to the
 *   osu! authorization server with our client_id and requested scopes.
 *
 * Leg 2 (callback): osu! redirects back here with an authorization code →
 *   we exchange it for an access token, fetch the user profile, and either
 *   create a new local account or update the existing one.
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class OsuAuthController extends Controller
{
    /**
     * Redirect the browser to the osu! OAuth authorization page.
     *
     * Socialite builds the authorization URL using the credentials in
     * config/services.osu and appends a CSRF-protected `state` parameter
     * to prevent cross-site request forgery on the callback.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect()
    {
        // Socialite reads 'osu' driver → OsuSocialiteProvider::getAuthUrl()
        return Socialite::driver('osu')->redirect();
    }

    /**
     * Handle the callback request from osu! after the user grants (or denies) access.
     *
     * osu! sends back:
     *   - code:  the authorization code we exchange for an access token
     *   - state: must match the state we sent; Socialite verifies this automatically
     *
     * On success we upsert the local User record with the latest tokens and log in.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback()
    {
        try {
            /*
             * Exchange the one-time authorization code for:
             *   - access_token  (used in API requests as Bearer token)
             *   - refresh_token (used to renew the access token when it expires)
             *   - user profile  (id, username, avatar_url, …)
             *
             * Socialite handles the HTTP POST to the token endpoint internally.
             * If the user denied access on the osu! side, an exception is thrown.
             */
            $osuUser = Socialite::driver('osu')->user();
        } catch (\Exception $e) {
            // Common causes: user clicked "Cancel", state mismatch, network error
            return redirect()->route('login')
                ->with('error', 'osu! authentication failed. Please try again.');
        }

        /*
         * Find an existing user by osu_id, or create a brand-new one.
         *
         * We use osu_id as the unique identifier rather than email because:
         *   1. osu! does not always expose the email via its API.
         *   2. A user might change their email on osu! but the ID never changes.
         *
         * If the user has logged in before, we refresh their tokens so API calls
         * continue to work after the previous token expires.
         */
        $user = User::updateOrCreate(
            // Search key: match on osu! user ID (stored as string for safety)
            ['osu_id' => (string) $osuUser->getId()],

            // Fields to set/update on every login
            [
                'name'               => $osuUser->getName(),
                'osu_username'       => $osuUser->getNickname(),
                'osu_access_token'   => $osuUser->token,
                'osu_refresh_token'  => $osuUser->refreshToken,

                /*
                 * Email: osu! API v2 does not return the email address in the
                 * public 'identify' scope response. We use a deterministic
                 * placeholder so Laravel's unique-email constraint is satisfied
                 * without storing a real email we don't have.
                 *
                 * The placeholder format  <osu_id>@osu.placeholder  is stable
                 * across logins for the same user and clearly fake.
                 */
                'email' => $osuUser->getEmail()
                    ?? ($osuUser->getId() . '@osu.placeholder'),
            ]
        );

        // Blocked users must not be let in — redirect with a clear message
        if ($user->is_blocked) {
            return redirect()->route('login')
                ->with('error', 'Your account has been blocked. Please contact an administrator.');
        }

        /*
         * Log the user in with "remember me" enabled (second param = true).
         * session()->regenerate() rotates the session ID to prevent session
         * fixation attacks where an attacker pre-sets a known session ID.
         */
        Auth::login($user, true);
        request()->session()->regenerate();

        // redirect()->intended() returns the user to the URL they originally
        // tried to visit before being sent to the login page, defaulting to /dashboard
        return redirect()->intended(route('dashboard'));
    }
}
