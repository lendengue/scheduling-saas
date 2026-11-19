<?php

namespace App\Http\Controllers\Booking;

use App\Actions\Appointments\CancelAppointment;
use App\Actions\Appointments\CustomerChangeWindow;
use App\Actions\Appointments\RescheduleAppointment;
use App\Enums\BookingChannel;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Availability\AvailabilityCalculator;
use App\Services\Availability\Slot;
use App\Support\Booking\AppointmentSummary;
use App\Support\Booking\PublicBusiness;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's management link: /appointments/{ulid}/manage?token=...
 *
 * Customers have no account; the random token (stored hashed) is the only
 * credential. A wrong token answers 404 and reveals nothing.
 */
class ManageAppointmentController extends Controller
{
    public function show(Request $request, Appointment $appointment, CurrentTenant $currentTenant): Response
    {
        $token = $this->authorizeToken($request, $appointment);
        $tenant = $currentTenant->getOrFail();
        $local = CustomerChangeWindow::deadline($appointment)->tz($tenant->timezone);

        return Inertia::render('Booking/Manage', [
            'business' => PublicBusiness::from($tenant),
            'appointment' => AppointmentSummary::from($appointment, $tenant),
            'token' => $token,
            'canChange' => CustomerChangeWindow::isOpen($appointment),
            'isActive' => $appointment->status->isBlocking(),
            'changeDeadline' => $local->format('Y-m-d H:i'),
            'cancellationNoticeHours' => $tenant->bookingSettings()->cancellationNoticeHours,
            'calendarUrl' => route('booking.calendar', ['appointment' => $appointment->id, 'token' => $token], absolute: false),
            'today' => CarbonImmutable::now($tenant->timezone)->format('Y-m-d'),
            'maxDate' => CarbonImmutable::now($tenant->timezone)->addDays($tenant->bookingSettings()->maxAdvanceDays)->format('Y-m-d'),
        ]);
    }

    public function cancel(Request $request, Appointment $appointment, CancelAppointment $cancel): RedirectResponse
    {
        $token = $this->authorizeToken($request, $appointment);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $reason = trim((string) ($validated['reason'] ?? ''));

        $cancel->handle($appointment, $reason === '' ? null : $reason, byCustomer: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your appointment was cancelled.')]);

        return redirect()->route('appointments.manage', ['appointment' => $appointment->id, 'token' => $token]);
    }

    public function reschedule(Request $request, Appointment $appointment, RescheduleAppointment $reschedule): RedirectResponse
    {
        $token = $this->authorizeToken($request, $appointment);
        $validated = $request->validate(['starts_at' => ['required', 'date']]);

        $reschedule->handle($appointment, CarbonImmutable::parse($validated['starts_at']), BookingChannel::Public);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your appointment was rescheduled.')]);

        return redirect()->route('appointments.manage', ['appointment' => $appointment->id, 'token' => $token]);
    }

    /**
     * Days with free slots for the same service and professional, counting
     * the appointment's current time as free.
     */
    public function days(Request $request, Appointment $appointment, AvailabilityCalculator $calculator): JsonResponse
    {
        $this->authorizeToken($request, $appointment);
        $month = $request->validate(['month' => ['required', 'date_format:Y-m']])['month'];
        [$year, $monthNumber] = array_map(intval(...), explode('-', $month));

        return response()->json([
            'days' => $calculator->availableDaysInMonth($appointment->service, $appointment->staffProfile, $year, $monthNumber, $appointment->id),
        ]);
    }

    public function slots(Request $request, Appointment $appointment, AvailabilityCalculator $calculator): JsonResponse
    {
        $this->authorizeToken($request, $appointment);
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];

        return response()->json([
            'slots' => $calculator->slotsFor($appointment->service, $appointment->staffProfile, CarbonImmutable::createFromFormat('!Y-m-d', $date) ?? abort(422), $appointment->id)
                ->map(fn (Slot $slot) => ['starts_at' => $slot->toArray()['starts_at'], 'label' => $slot->label])
                ->values(),
        ]);
    }

    private function authorizeToken(Request $request, Appointment $appointment): string
    {
        $token = $request->string('token')->toString();

        abort_unless($appointment->matchesManageToken($token), 404);

        return $token;
    }
}
