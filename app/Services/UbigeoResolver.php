<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UbigeoDistrict;

/**
 * Resuelve un código de ubigeo SUNAT (6 dígitos) a su tripleta
 * departamento/provincia/distrito.
 *
 * Diseño:
 *  - Memo in-process (estático). El catálogo INEI no muta y los lookups
 *    son por PK indexada — no vale la pena un cache cross-request hoy.
 *    Cuando una request resuelve el mismo ubigeo varias veces (factura
 *    + guía + warehouse en el mismo flujo), todos los hits posteriores
 *    son O(1) en memoria.
 *  - No se usa Cache facade porque CacheTenancyBootstrapper lo envuelve
 *    con tags y el driver `database` no los soporta.
 *  - Devuelve null si el código no existe (no lanza excepción).
 *
 * Usado por:
 *  - GreenterInvoiceService → llenar departamento/provincia/distrito
 *    en el Address SUNAT (mata warnings 4096/4097/4098).
 *  - GreenterDespatchService → ídem en GRE (Direction de partida/llegada).
 *  - Validación: rule UbigeoExists.
 */
final class UbigeoResolver
{
    /**
     * Memo por proceso. Key = código 6 dígitos, value = tripleta o `false`
     * (negative cache para códigos inválidos, evita re-querar lo inexistente).
     *
     * @var array<string, array{ubigeo: string, department: string, province: string, district: string}|false>
     */
    private static array $memo = [];

    /**
     * Devuelve la tripleta para un ubigeo de 6 dígitos, o null si no existe.
     *
     * @return array{ubigeo: string, department: string, province: string, district: string}|null
     */
    public function resolve(?string $ubigeo): ?array
    {
        $code = (string) $ubigeo;
        if (strlen($code) !== 6 || ! ctype_digit($code)) {
            return null;
        }

        if (array_key_exists($code, self::$memo)) {
            return self::$memo[$code] === false ? null : self::$memo[$code];
        }

        $district = UbigeoDistrict::query()
            ->with(['province', 'department'])
            ->find($code);

        if (! $district) {
            self::$memo[$code] = false;

            return null;
        }

        $result = [
            'ubigeo' => (string) $district->id,
            'district' => (string) $district->name,
            'province' => (string) ($district->province ? $district->province->name : ''),
            'department' => (string) ($district->department ? $district->department->name : ''),
        ];
        self::$memo[$code] = $result;

        return $result;
    }

    /**
     * Verificación de existencia (más barata que resolve cuando sólo
     * importa saber si el código es válido).
     */
    public function exists(?string $ubigeo): bool
    {
        return $this->resolve($ubigeo) !== null;
    }
}
