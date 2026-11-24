<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointments\AgendaEventsRequest;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\WorkingHour;
use App\Support\Agenda\AgendaFeed;
use App\Support\CurrentTenant;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The team's agenda. Owners and admins see every professional (and may
 * filter by one); staff members see only their own agenda.
 */
class AgendaController extends Controller
{
    public function index(#[CurrentUser] User $user, CurrentTenant $currentTenant): Response
    {
        Gate::authorize('viewAny', Appointment::class);

        $tenant = $currentTenant->getOrFail();
        $viewAll = $user->can('viewAll', Appointment::class);

        $profiles = $viewAll
            ? StaffProfile::query()->bookable()->with(['user:id,name', 'workingHours'])->get()->sortBy('user.name')->values()
            : collect([StaffProfile::forUser($user)->load(['user:id,name', 'workingHours'])]);

        return Inertia::render('Tenant/Agenda', [
            'timezone' => $tenant->timezone,
            'locale' => $tenant->locale,
            'staff' => $profiles->map(fn (StaffProfile $profile) => [
                'id' => $profile->id,
                'name' => $profile->user->name,
                'color' => $profile->color,
                // FullCalendar businessHours: shades the time outside working hours.
                'business_hours' => $profile->workingHours->map(fn (WorkingHour $hour) => [
                    'daysOfWeek' => [$hour->weekday],
                    'startTime' => substr($hour->starts_at, 0, 5),
                    'endTime' => substr($hour->ends_at, 0, 5),
                ])->values(),
            ]),
            'services' => Service::query()->ordered()->get(['id', 'name', 'duration_minutes', 'buffer_after_minutes', 'is_active'])
                ->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                    'buffer_after_minutes' => $service->buffer_after_minutes,
                    'is_active' => $service->is_active,
                ]),
            'can' => ['viewAll' => $viewAll],
        ]);
    }

    public function events(AgendaEventsRequest $request, #[CurrentUser] User $user, CurrentTenant $currentTenant): JsonResponse
    {
        $staffIds = $user->can('viewAll', Appointment::class)
            ? ($request->staffProfileId() === null ? null : [$request->staffProfileId()])
            // Staff members only ever get their own agenda, whatever they ask for.
            : [StaffProfile::forUser($user)->id];

        $feed = new AgendaFeed($currentTenant->getOrFail(), $user);

        return response()->json($feed->events($request->from(), $request->to(), $staffIds, $request->boolean('include_cancelled')));
    }
}
