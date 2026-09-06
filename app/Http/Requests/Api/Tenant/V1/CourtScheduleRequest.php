<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class CourtScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isPost = $this->isMethod('post');

        $rules = [
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'price' => ['required', 'numeric', 'min:0'],
            'slot_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($isPost) {
            // Bulk create: el frontend manda un array de días para replicar
            // el mismo horario en cada uno (caso típico: L-V con misma franja).
            $rules['day_of_week'] = ['required', 'array', 'min:1'];
            $rules['day_of_week.*'] = ['integer', 'between:0,6', 'distinct'];
        } else {
            $rules['day_of_week'] = ['required', 'integer', 'between:0,6'];
        }

        if ($this->isMethod('patch')) {
            $rules = array_map(function (array $rule): array {
                array_unshift($rule, 'sometimes');

                return $rule;
            }, $rules);
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $start = $this->input('start_time');
            $end = $this->input('end_time');

            if ($start && $end && $end <= $start) {
                $v->errors()->add('end_time', 'La hora de fin debe ser mayor a la hora de inicio.');
            }
        });
    }
}
