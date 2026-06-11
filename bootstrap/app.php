<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Append CheckBlocked to the 'web' middleware group so it runs on
         * every browser request after session handling is done. This ensures
         * blocked users are logged out immediately on their next page load,
         * even if they were already authenticated when the block was applied.
         */
        $middleware->web(append: [
            \App\Http\Middleware\CheckBlocked::class,
        ]);

        /*
         * Register named middleware aliases so route definitions can reference
         * them by a short key rather than the full class name.
         *
         * 'admin' — restricts routes to users where is_admin = true (HTTP 403 otherwise)
         */
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
