<?php

namespace App\Http\Requests\Services;

use App\Models\Service;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Service::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => [
                'integer', 'distinct',
                Rule::exists(Service::class, 'id')->where('tenant_id', app(CurrentTenant::class)->id())->withoutTrashed(),
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public function orderedIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('ids')));
    }
}
