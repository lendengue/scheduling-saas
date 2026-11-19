<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only allows the route on a tenant subdomain.
 */
class EnsureTenantContext
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->currentTenant->check(), 404);

        return $next($request);
    }
}
