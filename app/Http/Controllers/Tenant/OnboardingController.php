<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\WorkingHour;
use App\Support\CurrentTenant;
use App\Support\Money;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First-run wizard shown after registration: add services, set your own
 * working hours and (optionally) invite the team. Each step saves through
 * the regular endpoints and comes back here.
 */
class OnboardingController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, CurrentTenant $currentTenant): Response
    {
        $tenant = $currentTenant->getOrFail();
        // The owner takes bookings from the start (CreateTenant).
        $profile = StaffProfile::forUser($user)->load('workingHours');

        return Inertia::render('Tenant/Onboarding', [
            'canManageServices' => $user->can('create', Service::class),
            'services' => Service::query()->ordered()->get()->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'price_formatted' => Money::format($service->price_cents, $tenant),
            ]),
            'staff' => StaffProfile::query()->bookable()->with('user:id,name')->get()
                ->map(fn (StaffProfile $staff) => [
                    'id' => $staff->id,
                    'name' => $staff->user->name,
                    'color' => $staff->color,
                ])
                ->sortBy('name')
                ->values(),
            'hours' => $profile->workingHours->map(fn (WorkingHour $hour) => [
                'weekday' => $hour->weekday,
                'starts_at' => substr($hour->starts_at, 0, 5),
                'ends_at' => substr($hour->ends_at, 0, 5),
            ]),
            'canInvite' => $user->can('create', Invitation::class),
            'roles' => Role::invitableOptions(),
            'invitations' => Invitation::query()->pending()->latest()->get(['id', 'email', 'role'])
                ->map(fn (Invitation $invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->label(),
                ]),
            'bookingUrl' => $tenant->url(route('booking.show', absolute: false)),
        ]);
    }
}
