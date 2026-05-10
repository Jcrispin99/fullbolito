<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;

final class BillingCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('patch') || $this->isMethod('put');

        $rules = [
            'name' => 'required|string|max:255',
            'sol_user' => 'required|string|max:255',
            // En update, la password puede omitirse (mantener la actual).
            'sol_pass' => ($isUpdate ? 'nullable' : 'required').'|string|max:255',
            // Credenciales API son opcionales (no todos los proveedores las usan).
            'client_id' => 'nullable|string|max:255',
            'client_secret' => 'nullable|string|max:255',
            'production' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            // Archivo de certificado: opcional en update (mantiene el existente).
            'cert_file' => ($isUpdate ? 'nullable' : 'required').'|file|mimetypes:application/x-pkcs12,application/x-x509-ca-cert,application/pkix-cert,application/x-pem-file,text/plain,application/octet-stream|max:5120',
        ];

        if ($isUpdate) {
            // Sólo `name` siempre presente; los demás son sometimes para
            // permitir actualizaciones parciales (ej: sólo togglear production).
            $partialFields = ['sol_user', 'client_id', 'client_secret'];
            foreach ($partialFields as $field) {
                if (isset($rules[$field])) {
                    $rules[$field] = 'sometimes|'.$rules[$field];
                }
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cert_file.required' => 'Debes subir el archivo del certificado digital.',
            'cert_file.mimetypes' => 'El certificado debe ser .pem, .crt, .cer o .pfx (PKCS#12).',
            'cert_file.max' => 'El certificado no debe superar 5 MB.',
        ];
    }
}
