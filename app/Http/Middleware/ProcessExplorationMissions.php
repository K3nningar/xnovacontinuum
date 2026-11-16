<?php

namespace App\Http\Middleware;

use App\Services\ExplorationMissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProcessExplorationMissions
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app(ExplorationMissions::class)->processDue();

        return $next($request);
    }
}
