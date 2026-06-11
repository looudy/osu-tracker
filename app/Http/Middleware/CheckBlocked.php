<?php

/**
 * CheckBlocked Middleware
 *
 * Runs on every authenticated web request (added to the 'web' middleware group
 * in bootstrap/app.php). Ensures that if an administrator blocks a user while
 * they have an active session, that session is invalidated immediately on the
 * next request — the user cannot continue browsing after being blocked.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckBlocked
{
    /**
     * Handle an incoming request.
     *
     * Checks whether the currently authenticated user has the `is_blocked`
     * flag set to true. If so, logs them out and redirects to the login page
     * with an informative error message.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check authenticated users — guests pass through untouched
        if (Auth::check() && Auth::user()->is_blocked) {
            /*
             * Force logout sequence:
             *   1. Auth::logout()              — clears the auth session data
             *   2. session()->invalidate()     — destroys the session entirely
             *   3. session()->regenerateToken() — issues a fresh CSRF token so
             *                                    the next form submission is safe
             */
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Your account has been blocked. Please contact an administrator.');
        }

        // User is not blocked — continue to the intended route
        return $next($request);
    }
}
