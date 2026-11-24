<?php

namespace App\Http\Requests\Appointments;

use App\Data\Appointments\BookingData;
use App\Enums\BookingChannel;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Support\CurrentTenant;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * An appointment booked by the team from the agenda. Unlike public bookings,
 * it may be placed at any free time (walk-ins, phone bookings); only overlaps
 * are rejected.
 */
class StoreAppointmentRequest extends FormRequest
{
    use ReadsStartsAt;

    public function authorize(): bool
    {
        $profile = $this->staffProfile();

        // An unknown professional is reported by validation.
        return $profile === null || ($this->user()?->can('createFor', [Appointment::class, $profile]) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        $this->merge([
            'email' => is_string($this->input('email')) ? Str::lower(trim($this->input('email'))) : $this->input('email'),
            'phone' => is_string($phone) && trim($phone) !== '' ? (PhoneNumber::toE164($phone) ?? $phone) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'service_id' => [
                'required', 'integer',
                Rule::exists(Service::class, 'id')->where('tenant_id', $tenantId)->withoutTrashed(),
            ],
            'staff_profile_id' => [
                'required', 'integer',
                Rule::exists(StaffProfile::class, 'id')->where('tenant_id', $tenantId),
            ],
            'starts_at' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:'.PhoneNumber::E164_PATTERN],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number, with area code.',
        ];
    }

    public function toBookingData(): BookingData
    {
        $notes = $this->string('notes')->trim()->toString();
        $phone = $this->input('phone');

        return new BookingData(
            service: Service::query()->findOrFail($this->integer('service_id')),
            staff: $this->staffProfile() ?? abort(422),
            startsAt: $this->startsAt(),
            customerName: $this->string('name')->trim()->toString(),
            customerEmail: $this->string('email')->toString(),
            customerPhone: is_string($phone) ? $phone : null,
            notes: $notes === '' ? null : $notes,
            channel: BookingChannel::Panel,
        );
    }

    private function staffProfile(): ?StaffProfile
    {
        $id = $this->input('staff_profile_id');

        return is_numeric($id) ? StaffProfile::query()->find((int) $id) : null;
    }
}
