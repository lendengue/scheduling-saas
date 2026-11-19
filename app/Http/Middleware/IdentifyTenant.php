<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\CurrentTenant;
use App\Support\Tenancy\TenantDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the request host and stores it in CurrentTenant.
 *
 * - "{APP_DOMAIN}"        → central context (no tenant)
 * - "{slug}.{APP_DOMAIN}" → that tenant, or 404 if it does not exist
 * - any other host        → 404
 *
 * Runs first in the "web" group, before the session and the authenticated
 * user are resolved, so every query of the request is already scoped.
 */
class IdentifyTenant
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if (TenantDomain::isCentral($host)) {
            $this->currentTenant->forget();

            return $next($request);
        }

        $slug = TenantDomain::slugFromHost($host);
        $tenant = $slug === null ? null : Tenant::query()->where('slug', $slug)->first();

        abort_if($tenant === null, 404);

        $this->currentTenant->set($tenant);

        return $next($request);
    }
}
