<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Support\Booking\PublicBusiness;
use App\Support\CurrentTenant;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public booking page: {slug}.{APP_DOMAIN}/book.
 */
class BookingPageController extends Controller
{
    public function __invoke(CurrentTenant $currentTenant): Response
    {
        $tenant = $currentTenant->getOrFail();
        $today = CarbonImmutable::now($tenant->timezone)->startOfDay();

        $staff = StaffProfile::query()
            ->bookable()
            ->whereHas('services', fn ($query) => $query->where('is_active', true))
            ->with('user:id,name')
            ->get()
            ->sortBy(fn (StaffProfile $profile) => $profile->user->name)
            ->values();

        $services = Service::query()
            ->active()
            ->ordered()
            ->with(['staffProfiles' => fn ($query) => $query->where('is_bookable', true)->select('staff_profiles.id')])
            ->get()
            ->filter(fn (Service $service) => $service->staffProfiles->isNotEmpty())
            ->values();

        return Inertia::render('Booking/Show', [
            'business' => PublicBusiness::from($tenant),
            'services' => $services->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'duration_minutes' => $service->duration_minutes,
                'price_formatted' => Money::format($service->price_cents, $tenant),
                'staff_profile_ids' => $service->staffProfiles->modelKeys(),
            ]),
            'staff' => $staff->map(fn (StaffProfile $profile) => [
                'id' => $profile->id,
                'name' => $profile->user->name,
                'bio' => $profile->bio,
                'color' => $profile->color,
            ]),
            'today' => $today->format('Y-m-d'),
            'maxDate' => $today->addDays($tenant->bookingSettings()->maxAdvanceDays)->format('Y-m-d'),
        ]);
    }
}
