<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Applies the ProcessExplorationMissions HTTP middleware.
 */

namespace App\Http\Middleware;

use App\Services\ExplorationMissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProcessExplorationMissions
{
    /**
     * Handle an incoming request after settling due fleet arrivals and returns.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app(ExplorationMissions::class)->processDue();

        return $next($request);
    }
}
