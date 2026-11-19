<?php

namespace App\Http\Controllers\Central;

use App\Actions\Tenants\CreateTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\RegisterTenantRequest;
use App\Support\Tenancy\LoginHandoff;
use DateTimeZone;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RegisterTenantController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Central/Register', [
            'timezones' => DateTimeZone::listIdentifiers(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Create the tenant and send the owner straight to the onboarding wizard
     * on the new subdomain, already logged in.
     */
    public function store(RegisterTenantRequest $request, CreateTenant $createTenant, LoginHandoff $handoff): SymfonyResponse
    {
        $created = $createTenant->handle($request->toData());

        return Inertia::location($handoff->issue($created->owner, '/app/onboarding'));
    }
}
