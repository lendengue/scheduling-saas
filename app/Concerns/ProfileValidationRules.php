<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, Unique|ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        // Emails are unique per tenant (super admins form their own group).
        $tenantId = app(CurrentTenant::class)->id();
        $unique = Rule::unique(User::class, 'email');
        $unique = $tenantId === null ? $unique->whereNull('tenant_id') : $unique->where('tenant_id', $tenantId);

        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null ? $unique : $unique->ignore($userId),
        ];
    }
}
