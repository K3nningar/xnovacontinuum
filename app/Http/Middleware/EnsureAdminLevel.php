<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminLevel
{
    public function handle(Request $request, Closure $next, int $minimumLevel): Response
    {
        $user = $request->user();
        abort_unless($user, 403);
        $access = app(AdminAccess::class);
        $ids = $access->staffUniverses($user)->all();
        $request->attributes->set('admin_universe_ids', $ids);
        if ($minimumLevel >= 3) {
            abort_unless($access->isGlobal($user, $minimumLevel), 403);
        } else {
            abort_unless($access->isGlobal($user, 3) || $access->staffUniverses($user, $minimumLevel)->isNotEmpty(), 403);
        }

        return $next($request);
    }
}
