<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\AvailabilityRequest;
use App\Services\Availability\AvailabilityCalculator;
use App\Services\Availability\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * JSON endpoints behind the booking calendar.
 */
class AvailabilityController extends Controller
{
    public function days(AvailabilityRequest $request, AvailabilityCalculator $calculator): JsonResponse
    {
        [$year, $month] = array_map(intval(...), explode('-', $request->string('month')->toString()));

        return response()->json([
            'days' => $calculator->availableDaysInMonth($request->service(), $request->staff(), $year, $month),
        ]);
    }

    public function slots(AvailabilityRequest $request, AvailabilityCalculator $calculator): JsonResponse
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $request->string('date')->toString()) ?? abort(422);

        return response()->json([
            'slots' => $calculator->slotsFor($request->service(), $request->staff(), $date)
                ->map(fn (Slot $slot) => ['starts_at' => $slot->toArray()['starts_at'], 'label' => $slot->label])
                ->values(),
        ]);
    }
}
