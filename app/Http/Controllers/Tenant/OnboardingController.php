<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First-run wizard shown after registration. The services and working hours
 * steps arrive with the scheduling core.
 */
class OnboardingController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Tenant/Onboarding');
    }
}
