<?php

namespace App\Http\Requests\Appointments;

use App\Models\Appointment;
use App\Models\StaffProfile;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The period FullCalendar asks for (?start=...&end=..., ISO 8601).
 */
class AgendaEventsRequest extends FormRequest
{
    /**
     * Longest period served at once: the list and week views ask for far less.
     */
    public const int MAX_DAYS = 62;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Appointment::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'staff_profile_id' => [
                'nullable', 'integer',
                Rule::exists(StaffProfile::class, 'id')->where('tenant_id', app(CurrentTenant::class)->id()),
            ],
            'include_cancelled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && $this->from()->diffInDays($this->to()) > self::MAX_DAYS) {
                    $validator->errors()->add('end', 'The period is too long.');
                }
            },
        ];
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('start')->toString(), $this->timezone())->utc();
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('end')->toString(), $this->timezone())->utc();
    }

    public function staffProfileId(): ?int
    {
        $id = $this->input('staff_profile_id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function timezone(): string
    {
        return app(CurrentTenant::class)->getOrFail()->timezone;
    }
}
