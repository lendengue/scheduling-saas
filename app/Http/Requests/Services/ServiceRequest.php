<?php

namespace App\Http\Requests\Services;

use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Creating and updating a service share the same rules. The price comes in
 * currency units ("35.90") and is stored in cents.
 */
class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service
            ? $this->user()?->can('update', $service) ?? false
            : $this->user()?->can('create', Service::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('price'))) {
            $this->merge(['price' => str_replace(',', '.', trim($this->input('price')))]);
        }

        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'between:5,480', 'multiple_of:5'],
            'buffer_after_minutes' => ['required', 'integer', 'between:0,120'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Validated attributes, ready for the model.
     *
     * @return array{name: string, description: string|null, duration_minutes: int, buffer_after_minutes: int, price_cents: int, is_active: bool}
     */
    public function serviceAttributes(): array
    {
        $description = $this->string('description')->trim()->toString();

        return [
            'name' => $this->string('name')->trim()->toString(),
            'description' => $description === '' ? null : $description,
            'duration_minutes' => $this->integer('duration_minutes'),
            'buffer_after_minutes' => $this->integer('buffer_after_minutes'),
            'price_cents' => (int) round((float) $this->input('price') * 100),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
