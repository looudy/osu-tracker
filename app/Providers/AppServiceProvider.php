<?php

/**
 * AppServiceProvider — The main application service provider.
 *
 * Used here to register our custom osu! OAuth driver with Laravel Socialite.
 * Because `socialiteproviders/osu` is not a standalone Packagist package, we
 * extend Socialite manually using its `extend()` API.
 */

namespace App\Providers;

use App\Models\Comment;
use App\Policies\CommentPolicy;
use App\Services\OsuSocialiteProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     * Called before boot() — bind interface implementations, singletons, etc.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     * Called after all providers are registered. We register the osu! Socialite
     * driver here because Socialite itself must already be registered first.
     */
    public function boot(): void
    {
        /*
         * Register the custom osu! OAuth2 provider with Laravel Socialite.
         *
         * Socialite::extend() lets us add arbitrary OAuth drivers.
         * The closure receives the IoC container and must return a provider
         * instance built via Socialite::buildProvider(), which wires up the
         * HTTP client, state manager, and other internals automatically.
         *
         * Config keys (client_id, client_secret, redirect) are read from
         * config/services.php under the 'osu' key, which in turn reads .env.
         */
        /*
         * Register the CommentPolicy so Laravel's Gate knows how to authorize
         * comment edit/delete actions throughout the application.
         * This maps the Comment model to its corresponding policy class.
         */
        Gate::policy(Comment::class, CommentPolicy::class);

        /*
         * Use our custom pagination template (resources/views/vendor/pagination/tracker.blade.php)
         * for all $paginator->links() calls. The framework default is a Tailwind template, but
         * this app uses raw CSS — its unstyled SVG arrows rendered oversized. Our template uses
         * plain text chevrons styled by public/css/style.css instead.
         */
        Paginator::defaultView('vendor.pagination.tracker');

        Socialite::extend('osu', function ($app) {
            // Pull our osu! credentials from the services config
            $config = $app['config']['services.osu'];

            // buildProvider() instantiates OsuSocialiteProvider and injects
            // the HTTP request, client_id, client_secret, and redirect URL
            return Socialite::buildProvider(OsuSocialiteProvider::class, $config);
        });
    }
}
