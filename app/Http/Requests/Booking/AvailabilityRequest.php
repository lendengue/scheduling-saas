<?php

namespace App\Http\Requests\Booking;

use App\Models\Service;
use App\Models\StaffProfile;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'month' => [Rule::requiredIf($this->routeIs('booking.availability.days')), 'date_format:Y-m'],
            'date' => [Rule::requiredIf($this->routeIs('booking.availability.slots')), 'date_format:Y-m-d'],
        ];
    }

    public function service(): Service
    {
        return Service::query()->findOrFail($this->integer('service_id'));
    }

    public function staff(): ?StaffProfile
    {
        $id = $this->input('staff_profile_id');

        return $id === null ? null : StaffProfile::query()->findOrFail((int) $id);
    }
}
