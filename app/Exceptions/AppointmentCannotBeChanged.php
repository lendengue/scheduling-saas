<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The appointment is no longer active, or it starts too soon for the
 * customer to change it online (booking_settings.cancellation_notice_hours).
 */
class AppointmentCannotBeChanged extends RuntimeException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 409);
        }

        return back()->withErrors(['appointment' => $this->getMessage()]);
    }
}
