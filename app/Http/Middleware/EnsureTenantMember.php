<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense in depth: the authenticated user must belong to the current tenant.
 *
 * The tenant scope already prevents a session from resolving a user of
 * another tenant, but this guard also covers users set on the guard directly
 * (tokens, impersonation, tests).
 */
class EnsureTenantMember
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null || $user->tenant_id === null || $user->tenant_id !== $this->currentTenant->id(), 403);

        return $next($request);
    }
}
