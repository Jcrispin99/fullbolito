<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use App\Rules\UbigeoExists;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for creating and updating a supplier (Partner).
 */
final class SupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $uniqueRule = 'unique:partners,document_number';
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $partner = $this->route('supplier') ?? $this->route('partner');
            $partnerId = $partner ? $partner->id : null;
            if ($partnerId) {
                $uniqueRule = 'unique:partners,document_number,'.$partnerId;
            }
        }

        return [
            'is_supplier' => 'required|boolean',
            'is_customer' => 'nullable|boolean',
            'document_type' => 'nullable|in:DNI,RUC,CE,Passport',
            'document_number' => ['nullable', 'string', 'max:20', $uniqueRule],
            'name' => 'required|string|max:200',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'ubigeo' => ['nullable', 'string', 'size:6', new UbigeoExists()],
            'payment_terms' => 'nullable|string',
            'provider_category' => 'nullable|string|max:50',
            'status' => 'required|in:active,inactive,suspended,blacklisted',
            'notes' => 'nullable|string',
        ];
    }
}
