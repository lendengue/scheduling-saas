<?php

namespace App\Http\Requests\Booking;

use App\Data\Appointments\BookingData;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Support\CurrentTenant;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A customer's booking from the public page. Anyone can submit it; abuse is
 * limited by a per-IP rate limit and a honeypot field ("website") that real
 * visitors never see.
 */
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        $this->merge([
            'email' => is_string($this->input('email')) ? Str::lower(trim($this->input('email'))) : $this->input('email'),
            // Keep what was typed when it cannot be normalized, so the regex rule reports it.
            'phone' => is_string($phone) ? (PhoneNumber::toE164($phone) ?? $phone) : $phone,
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
                Rule::exists(Service::class, 'id')->where('tenant_id', $tenantId)->where('is_active', true)->withoutTrashed(),
            ],
            'staff_profile_id' => [
                'nullable', 'integer',
                Rule::exists(StaffProfile::class, 'id')->where('tenant_id', $tenantId)->where('is_bookable', true),
            ],
            'starts_at' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::E164_PATTERN],
            'notes' => ['nullable', 'string', 'max:1000'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number, with area code.',
            'website.prohibited' => 'Your booking could not be processed.',
        ];
    }

    public function toBookingData(): BookingData
    {
        $staffId = $this->input('staff_profile_id');
        $notes = $this->string('notes')->trim()->toString();

        return new BookingData(
            service: Service::query()->findOrFail($this->integer('service_id')),
            staff: $staffId === null ? null : StaffProfile::query()->findOrFail((int) $staffId),
            startsAt: CarbonImmutable::parse($this->string('starts_at')->toString())->utc(),
            customerName: $this->string('name')->trim()->toString(),
            customerEmail: $this->string('email')->toString(),
            customerPhone: $this->string('phone')->toString(),
            notes: $notes === '' ? null : $notes,
        );
    }
}
