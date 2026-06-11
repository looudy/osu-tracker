<?php

/**
 * AdminMiddleware — protects all admin routes from non-admin users.
 * Returns 403 if the authenticated user does not have is_admin = true.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Block non-admin users from accessing admin routes.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next     The next middleware handler
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
