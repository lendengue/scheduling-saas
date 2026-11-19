<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * The chosen time was taken (or became unavailable) before the booking was
 * saved. Raised both by the availability check and, for true races, by the
 * database exclusion constraint (SQLSTATE 23P01).
 */
class SlotNoLongerAvailable extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Sorry, this time is no longer available. Please choose another one.', 0, $previous);
    }

    /**
     * 409 Conflict for API clients, a friendly validation error otherwise.
     */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 409);
        }

        return back()->withErrors(['starts_at' => $this->getMessage()]);
    }
}
