<?php

namespace App\Http\Controllers;

use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Landing page on the central domain; the panel on a tenant subdomain.
     * (The public booking page takes over the tenant root in Phase 2.)
     */
    public function __invoke(CurrentTenant $currentTenant): Response|RedirectResponse
    {
        if ($currentTenant->check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Welcome');
    }
}
