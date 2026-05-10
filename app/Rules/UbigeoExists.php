<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\UbigeoResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que un código de ubigeo (6 dígitos) exista en el catálogo INEI
 * (base central). Reemplaza al `exists:` tradicional para no exponer la
 * connection central al validador, y permite mensajes en español.
 *
 * Uso típico en FormRequest:
 *   'ubigeo' => ['required', 'string', 'size:6', new UbigeoExists()],
 */
final class UbigeoExists implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $resolver = app(UbigeoResolver::class);

        if (! $resolver->exists(is_string($value) ? $value : null)) {
            $fail('El :attribute no corresponde a un ubigeo INEI válido.');
        }
    }
}
