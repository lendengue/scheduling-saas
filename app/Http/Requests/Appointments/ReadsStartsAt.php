<?php

namespace App\Http\Requests\Appointments;

use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The agenda sends wall-clock times of the business ("2026-11-24T14:30");
 * a value with an explicit offset is taken as is.
 *
 * @mixin FormRequest
 */
trait ReadsStartsAt
{
    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $this->string('starts_at')->toString(),
            app(CurrentTenant::class)->getOrFail()->timezone,
        )->utc();
    }
}
