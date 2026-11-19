<?php

namespace App\Http\Requests\Staff;

use App\Enums\Role;
use App\Models\Service;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffMemberRequest extends FormRequest
{
    use ResolvesStaffProfile;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->profile()) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_bookable' => $this->boolean('is_bookable')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The owner's role is fixed; everyone else is an admin or staff.
            'role' => ['nullable', Rule::enum(Role::class)->only(Role::invitable())],
            'is_bookable' => ['boolean'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'service_ids' => ['array'],
            'service_ids.*' => [
                'integer', 'distinct',
                Rule::exists(Service::class, 'id')->where('tenant_id', app(CurrentTenant::class)->id())->withoutTrashed(),
            ],
        ];
    }

    public function role(): ?Role
    {
        return $this->enum('role', Role::class);
    }

    /**
     * @return array{bio: string|null, color: string, is_bookable: bool}
     */
    public function profileAttributes(): array
    {
        $bio = $this->string('bio')->trim()->toString();

        return [
            'bio' => $bio === '' ? null : $bio,
            'color' => strtolower($this->string('color')->toString()),
            'is_bookable' => $this->boolean('is_bookable'),
        ];
    }

    /**
     * @return list<int>
     */
    public function serviceIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('service_ids', [])));
    }
}
