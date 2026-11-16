<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminLevel
{
    /**
     * Handle an incoming request.
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
