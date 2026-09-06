<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\UbigeoDepartment;
use App\Models\UbigeoDistrict;
use App\Models\UbigeoProvince;
use App\Services\UbigeoResolver;
use Illuminate\Http\JsonResponse;

/**
 * Catálogo INEI (read-only) expuesto sin auth para los selectors del
 * marketplace público (`fullbolito.com`). Misma data que `Tenant\V1\UbigeoController`,
 * pero accesible desde el dominio central sin sanctum.
 */
final class PublicUbigeoController extends ApiController
{
    public function departments(): JsonResponse
    {
        return $this->success(
            UbigeoDepartment::query()->orderBy('name')->get(['id', 'name']),
            'Departments retrieved',
        );
    }

    public function provinces(string $departmentId): JsonResponse
    {
        return $this->success(
            UbigeoProvince::query()
                ->where('department_id', $departmentId)
                ->orderBy('name')
                ->get(['id', 'name', 'department_id']),
            'Provinces retrieved',
        );
    }

    public function districts(string $provinceId): JsonResponse
    {
        return $this->success(
            UbigeoDistrict::query()
                ->where('province_id', $provinceId)
                ->orderBy('name')
                ->get(['id', 'name', 'province_id', 'department_id']),
            'Districts retrieved',
        );
    }

    /**
     * Resuelve un código completo (6 dígitos) a la tripleta depto/provincia/distrito.
     * Útil al cargar una URL con `?ubigeo=150130` para preseleccionar el cascade.
     */
    public function resolve(string $code, UbigeoResolver $resolver): JsonResponse
    {
        $tripleta = $resolver->resolve($code);

        if ($tripleta === null) {
            return $this->error('Ubigeo no encontrado.', 404);
        }

        return $this->success($tripleta, 'Ubigeo resolved');
    }
}
