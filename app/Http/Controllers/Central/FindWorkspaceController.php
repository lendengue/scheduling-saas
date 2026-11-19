<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\FindWorkspaceRequest;
use App\Support\Tenancy\TenantDomain;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Central login: users belong to one workspace (subdomain), so the central
 * login page asks for the workspace and forwards to its login form.
 */
class FindWorkspaceController extends Controller
{
    public function __invoke(FindWorkspaceRequest $request): Response
    {
        return Inertia::location(TenantDomain::tenantUrl($request->string('slug')->toString(), '/login'));
    }
}
