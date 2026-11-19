<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Services\CreateService;
use App\Actions\Services\ReorderServices;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\ReorderServicesRequest;
use App\Http\Requests\Services\ServiceRequest;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Support\CurrentTenant;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(CurrentTenant $currentTenant): Response
    {
        Gate::authorize('viewAny', Service::class);

        $tenant = $currentTenant->getOrFail();

        return Inertia::render('Tenant/Services/Index', [
            'services' => Service::query()->with('staffProfiles:id')->ordered()->get()->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'duration_minutes' => $service->duration_minutes,
                'buffer_after_minutes' => $service->buffer_after_minutes,
                'price' => number_format($service->price_cents / 100, 2, '.', ''),
                'price_formatted' => Money::format($service->price_cents, $tenant),
                'is_active' => $service->is_active,
                'staff_profile_ids' => $service->staffProfiles->modelKeys(),
            ]),
            'staff' => StaffProfile::query()->bookable()->with('user:id,name')->get()
                ->map(fn (StaffProfile $profile) => [
                    'id' => $profile->id,
                    'name' => $profile->user->name,
                    'color' => $profile->color,
                ])
                ->sortBy('name')
                ->values(),
        ]);
    }

    public function store(ServiceRequest $request, CreateService $create): RedirectResponse
    {
        $service = $create->handle($request->serviceAttributes());
        $service->staffProfiles()->sync($request->staffProfileIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name created.', ['name' => $service->name])]);

        return back();
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->serviceAttributes());
        $service->staffProfiles()->sync($request->staffProfileIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name updated.', ['name' => $service->name])]);

        return back();
    }

    public function destroy(Service $service): RedirectResponse
    {
        Gate::authorize('delete', $service);

        // Soft delete: past appointments keep pointing at the service.
        $service->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name deleted.', ['name' => $service->name])]);

        return back();
    }

    public function reorder(ReorderServicesRequest $request, ReorderServices $reorder): RedirectResponse
    {
        $reorder->handle($request->orderedIds());

        return back();
    }
}
