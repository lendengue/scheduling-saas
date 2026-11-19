<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Support\Calendar\AppointmentIcs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * "Add to calendar": the appointment as an .ics file, behind its token.
 */
class AppointmentCalendarController extends Controller
{
    public function __invoke(Request $request, Appointment $appointment): Response
    {
        abort_unless($appointment->matchesManageToken($request->string('token')->toString()), 404);

        return response(AppointmentIcs::build($appointment), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="appointment-'.$appointment->id.'.ics"',
        ]);
    }
}
