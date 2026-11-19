<?php

namespace App\Http\Requests\Team;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invitation::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class, 'email')->where('tenant_id', app(CurrentTenant::class)->id()),
            ],
            'role' => ['required', Rule::enum(Role::class)->only(Role::invitable())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This person is already a member of your team.',
        ];
    }

    public function role(): Role
    {
        return $this->enum('role', Role::class) ?? Role::Staff;
    }
}
