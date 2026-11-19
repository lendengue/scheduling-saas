<?php

namespace App\Http\Requests\Staff;

use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Time off is entered in the tenant's wall-clock time and stored in UTC.
 */
class StoreTimeOffRequest extends FormRequest
{
    use ResolvesStaffProfile;

    public function authorize(): bool
    {
        return $this->user()?->can('manageSchedule', $this->profile()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, reason: string|null}
     */
    public function timeOffAttributes(): array
    {
        $timezone = app(CurrentTenant::class)->getOrFail()->timezone;
        $reason = $this->string('reason')->trim()->toString();

        return [
            'starts_at' => CarbonImmutable::parse($this->string('starts_at')->toString(), $timezone)->utc(),
            'ends_at' => CarbonImmutable::parse($this->string('ends_at')->toString(), $timezone)->utc(),
            'reason' => $reason === '' ? null : $reason,
        ];
    }
}
