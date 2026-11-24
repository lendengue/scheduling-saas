<?php

namespace App\Http\Requests\Appointments;

use App\Models\Appointment;
use App\Models\StaffProfile;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Moving an appointment from the agenda (drag and drop or the dialog),
 * optionally to another professional.
 */
class RescheduleAppointmentRequest extends FormRequest
{
    use ReadsStartsAt;

    public function authorize(): bool
    {
        $user = $this->user();
        $profile = $this->staffProfile();

        return $user !== null
            && $user->can('update', $this->appointment())
            && ($profile === null || $user->can('createFor', [Appointment::class, $profile]));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'staff_profile_id' => [
                'nullable', 'integer',
                Rule::exists(StaffProfile::class, 'id')->where('tenant_id', app(CurrentTenant::class)->id()),
            ],
        ];
    }

    public function appointment(): Appointment
    {
        $appointment = $this->route('appointment');
        abort_unless($appointment instanceof Appointment, 404);

        return $appointment;
    }

    public function staffProfile(): ?StaffProfile
    {
        $id = $this->input('staff_profile_id');

        return is_numeric($id) ? StaffProfile::query()->find((int) $id) : null;
    }
}
