<?php

/**
 * OsuSocialiteProvider — Custom Laravel Socialite driver for osu! OAuth2.
 *
 * The `socialiteproviders/manager` package is a framework; individual providers
 * (like osu!) are separate installable packages. Since `socialiteproviders/osu`
 * is not available, we implement the osu! OAuth2 flow here directly by extending
 * Socialite's AbstractProvider.
 *
 * osu! OAuth2 docs: https://osu.ppy.sh/docs/index.html#authentication
 */

namespace App\Services;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class OsuSocialiteProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * Scopes requested during OAuth authorization.
     * - 'identify' : access the user's profile (id, username, avatar)
     * - 'public'   : read public data such as recent scores
     *
     * @var array<string>
     */
    protected $scopes = ['identify', 'public'];

    /**
     * osu! uses a space as the scope separator, not the default comma.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * Build the osu! authorization URL where users are redirected to grant access.
     * The $state parameter is a CSRF token that we verify in the callback.
     *
     * @param  string $state
     * @return string
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(
            'https://osu.ppy.sh/oauth/authorize',
            $state
        );
    }

    /**
     * Return the URL used to exchange the authorization code for an access token.
     *
     * @return string
     */
    protected function getTokenUrl(): string
    {
        return 'https://osu.ppy.sh/oauth/token';
    }

    /**
     * Fetch the authenticated user's profile from the osu! API v2 /me endpoint.
     * This is called after we successfully obtain an access token.
     *
     * @param  string $token  The bearer access token
     * @return array<string, mixed>  Raw user data from osu! API
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get('https://osu.ppy.sh/api/v2/me', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ]);

        // Decode JSON body into associative array
        return json_decode((string) $response->getBody(), true);
    }

    /**
     * Map the raw osu! API user object to Socialite's User model.
     * Only the fields required for login are mapped here; the full raw
     * data remains accessible via $user->getRaw() for storing additional info.
     *
     * @param  array<string, mixed> $user  Raw user data from getUserByToken()
     * @return \Laravel\Socialite\Two\User
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User())->setRaw($user)->map([
            // osu! user ID (integer) — our primary foreign key to osu!
            'id'       => $user['id'],

            // username shown publicly on osu! (e.g. "peppy")
            'nickname' => $user['username'],
            'name'     => $user['username'],

            // osu! does not expose the user's email via the public API;
            // some OAuth scopes may return it, but we default to null
            'email'    => $user['email'] ?? null,

            // Profile avatar URL (hosted on osu! CDN)
            'avatar'   => $user['avatar_url'] ?? null,
        ]);
    }
}
