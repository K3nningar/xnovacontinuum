<?php

namespace App\Http\Middleware;

use App\Services\Changelog;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private Changelog $changelog) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('xnova.locale');

        app()->setLocale(in_array($locale, ['fr', 'en', 'de', 'es', 'it'], true) ? $locale : config('app.locale'));

        return parent::handle($request, $next);
    }

    /**
     * Determines the current asset version.
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
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email', 'universe_id', 'alliance_id', 'admin_level'),
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
