<?php

namespace App\Http\Requests\Staff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorkingHoursRequest extends FormRequest
{
    use ResolvesStaffProfile;

    public function authorize(): bool
    {
        return $this->user()?->can('manageSchedule', $this->profile()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hours' => ['present', 'array', 'max:70'],
            'hours.*.weekday' => ['required', 'integer', 'between:0,6'],
            'hours.*.starts_at' => ['required', 'date_format:H:i'],
            'hours.*.ends_at' => ['required', 'date_format:H:i', 'after:hours.*.starts_at'],
        ];
    }

    /**
     * Intervals of the same day must not overlap.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach (collect($this->intervals())->groupBy('weekday') as $intervals) {
                    /** @var list<array{weekday: int, starts_at: string, ends_at: string}> $sorted */
                    $sorted = $intervals->sortBy('starts_at')->values()->all();

                    for ($i = 1; $i < count($sorted); $i++) {
                        if ($sorted[$i]['starts_at'] < $sorted[$i - 1]['ends_at']) {
                            $validator->errors()->add('hours', 'Working hours of the same day cannot overlap.');

                            return;
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return list<array{weekday: int, starts_at: string, ends_at: string}>
     */
    public function intervals(): array
    {
        /** @var list<array{weekday: int|string, starts_at: string, ends_at: string}> $hours */
        $hours = (array) $this->input('hours', []);

        return array_map(fn (array $interval) => [
            'weekday' => (int) $interval['weekday'],
            'starts_at' => $interval['starts_at'],
            'ends_at' => $interval['ends_at'],
        ], $hours);
    }
}
