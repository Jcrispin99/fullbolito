<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catálogo INEI de distritos (~1874). Central.
 *
 * El `id` (6 dígitos) es el código de ubigeo SUNAT — es el valor
 * que se almacena en columnas como `warehouses.ubigeo`,
 * `companies.ubigeo`, `partners.ubigeo`.
 *
 * @property string $id            6 dígitos: dpto(2) + prov(2) + dist(2)
 * @property string $name
 * @property string $province_id
 * @property string $department_id
 */
final class UbigeoDistrict extends Model
{
    protected $connection = 'mysql';

    protected $table = 'ubigeo_districts';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'name', 'province_id', 'department_id'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(UbigeoProvince::class, 'province_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(UbigeoDepartment::class, 'department_id');
    }
}
