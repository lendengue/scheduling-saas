<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only allows the route on the central domain.
 */
class EnsureCentralContext
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->currentTenant->check(), 404);

        return $next($request);
    }
}
