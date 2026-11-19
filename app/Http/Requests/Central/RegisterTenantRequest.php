<?php

namespace App\Http\Requests\Central;

use App\Concerns\PasswordValidationRules;
use App\Data\Tenants\NewTenantData;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterTenantRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * A valid DNS label: lowercase letters, digits and inner hyphens.
     */
    public const string SLUG_PATTERN = '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/';

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required', 'string', 'min:3', 'max:30', 'regex:'.self::SLUG_PATTERN,
                Rule::notIn(config('tenancy.reserved_slugs')),
                // Soft-deleted tenants keep their slug: subdomains are never recycled.
                Rule::unique(Tenant::class, 'slug'),
            ],
            'timezone' => ['required', 'string', 'timezone:all'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Use only lowercase letters, numbers and hyphens, starting and ending with a letter or number.',
            'slug.not_in' => 'This address is reserved. Please choose another one.',
            'slug.unique' => 'This address is already taken.',
        ];
    }

    public function toData(): NewTenantData
    {
        return new NewTenantData(
            businessName: $this->string('business_name')->trim()->toString(),
            slug: $this->string('slug')->toString(),
            timezone: $this->string('timezone')->toString(),
            ownerName: $this->string('name')->trim()->toString(),
            ownerEmail: $this->string('email')->toString(),
            ownerPassword: $this->string('password')->toString(),
            locale: app()->getLocale(),
        );
    }
}
