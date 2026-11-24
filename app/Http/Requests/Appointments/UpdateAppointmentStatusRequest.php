<?php

namespace App\Http\Requests\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment instanceof Appointment && ($this->user()?->can('update', $appointment) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Completed->value,
                AppointmentStatus::NoShow->value,
            ])],
        ];
    }

    public function status(): AppointmentStatus
    {
        return AppointmentStatus::from($this->string('status')->toString());
    }
}
