<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Applies the EnsureAdminLevel HTTP middleware.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminLevel
{
    /**
     * Handle an incoming request, requiring the route's minimum privilege and rejecting levels above founder.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, int $minimumLevel): Response
    {
        $level = (int) $request->user()?->admin_level;
        abort_unless($level >= $minimumLevel && $level <= 4, 403);

        return $next($request);
    }
}
