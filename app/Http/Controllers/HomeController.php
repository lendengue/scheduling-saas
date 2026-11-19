<?php

namespace App\Http\Controllers;

use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Landing page on the central domain; the public booking page on a
     * tenant subdomain (the team reaches the panel at /app).
     */
    public function __invoke(CurrentTenant $currentTenant): Response|RedirectResponse
    {
        if ($currentTenant->check()) {
            return redirect()->route('booking.show');
        }

        return Inertia::render('Welcome');
    }
}
