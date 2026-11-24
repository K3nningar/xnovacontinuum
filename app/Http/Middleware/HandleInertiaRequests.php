<?php

declare(strict_types=1);

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Applies the HandleInertiaRequests HTTP middleware.
 */

namespace App\Http\Middleware;

use App\Services\Changelog;
use App\Services\AdminAccess;
use App\Services\PublicAvatarUrl;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * Provides the current published game version for authenticated Inertia visits.
     */
    public function __construct(private Changelog $changelog) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Applies a supported locale cookie; unsupported or missing values use the configured application locale.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('xnova.locale');

        app()->setLocale(in_array($locale, ['fr', 'en', 'de', 'es', 'it'], true) ? $locale : config('app.locale'));

        return parent::handle($request, $next);
    }

    /**
     * Delegates asset version selection to the parent Inertia middleware.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * Shares the safe user projection, locale, deferred flash/version values, and request-specific Ziggy data.
     * Sensitive auth fields are excluded; the game version is resolved only for authenticated requests.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $authenticatedUser = $request->user();
        $commanderAccount = $authenticatedUser?->universe_id !== null
            ? $authenticatedUser->account
            : $authenticatedUser;

        return [
            ...parent::share($request),
            'auth' => [
                'twoFactorChallenge' => (bool) $request->session()->get('auth.two_factor_challenge', false),
                'user' => $request->user() ? [
                    ...$request->user()->only('id', 'name', 'email', 'universe_id', 'alliance_id', 'avatar_path'),
                    'admin_level' => app(AdminAccess::class)->highestLevel($request->user()),
                    'admin_global' => app(AdminAccess::class)->isGlobal($request->user(), 3),
                    'admin_universe_ids' => app(AdminAccess::class)->staffUniverses($request->user())->values(),
                    'avatar_url' => $commanderAccount?->avatar_path ? app(PublicAvatarUrl::class)->for($request, $commanderAccount->avatar_path) : null,
                ] : null,
            ],
            'locale' => app()->getLocale(),
            'gameVersion' => fn () => $request->user() ? $this->changelog->currentVersion() : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'url' => $request->getSchemeAndHttpHost(),
                'location' => $request->getSchemeAndHttpHost().$request->getRequestUri(),
            ],
        ];
    }
}
