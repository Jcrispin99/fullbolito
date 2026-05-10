<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\UbigeoDepartment;
use App\Models\UbigeoDistrict;
use App\Models\UbigeoProvince;
use App\Services\UbigeoResolver;
use Illuminate\Http\JsonResponse;

/**
 * Endpoints de lectura para el catálogo INEI (central). Read-only.
 *
 * Aunque el controller vive en `Tenant\V1`, las queries van a la base
 * central porque los modelos Ubigeo* declaran `$connection = 'mysql'`.
 * Esto permite que los formularios del tenant (Warehouse, Company,
 * Partner) consuman el catálogo sin replicar datos.
 *
 * Patrón cascada para el front: deptos → provincias → distritos.
 */
final class UbigeoController extends ApiController
{
    public function departments(): JsonResponse
    {
        $departments = UbigeoDepartment::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->success($departments, 'Departments retrieved');
    }

    public function provinces(string $departmentId): JsonResponse
    {
        $provinces = UbigeoProvince::query()
            ->where('department_id', $departmentId)
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);

        return $this->success($provinces, 'Provinces retrieved');
    }

    public function districts(string $provinceId): JsonResponse
    {
        $districts = UbigeoDistrict::query()
            ->where('province_id', $provinceId)
            ->orderBy('name')
            ->get(['id', 'name', 'province_id', 'department_id']);

        return $this->success($districts, 'Districts retrieved');
    }

    /**
     * Resuelve un código completo de 6 dígitos a su tripleta.
     * Útil para el form de edición: el front sólo guarda el `ubigeo`
     * pero al cargar necesita conocer depto/provincia para preseleccionar
     * los selects.
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
