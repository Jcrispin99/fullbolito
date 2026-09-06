<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class ReservationRequest extends FormRequest
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
        $rules = [
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],

            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'customer_name' => ['required_without:partner_id', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'customer_email' => ['nullable', 'email', 'max:255'],

            'total' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'held_until' => ['nullable', 'date'],

            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ];

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
            // No permitir crear reservas en el pasado (con margen de 5 min de drift)
            if ($this->isMethod('post') && $this->filled('start_at')) {
                $start = strtotime((string) $this->input('start_at'));
                if ($start !== false && $start < time() - 300) {
                    $v->errors()->add('start_at', 'No se puede crear una reserva en el pasado.');
                }
            }
        });
    }
}
