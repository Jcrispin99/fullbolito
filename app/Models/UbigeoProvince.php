<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo INEI de provincias (~196). Central.
 *
 * @property string $id            4 dígitos = department_id (2) + sufijo (2)
 * @property string $name
 * @property string $department_id
 */
final class UbigeoProvince extends Model
{
    protected $connection = 'mysql';

    protected $table = 'ubigeo_provinces';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'name', 'department_id'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(UbigeoDepartment::class, 'department_id');
    }

    public function districts(): HasMany
    {
        return $this->hasMany(UbigeoDistrict::class, 'province_id');
    }
}
