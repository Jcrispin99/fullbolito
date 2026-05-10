<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo INEI de departamentos (25). Vive en la base CENTRAL —
 * compartido entre todos los tenants.
 *
 * El `$connection` está fijado a `mysql` (la connection central) para
 * que el modelo siempre apunte a central, aún cuando se accede desde
 * dentro del contexto tenant (que cambia la connection default).
 *
 * @property string $id  2 dígitos con leading zeros — '01' a '25'
 * @property string $name
 */
final class UbigeoDepartment extends Model
{
    protected $connection = 'mysql';

    protected $table = 'ubigeo_departments';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'name'];

    public function provinces(): HasMany
    {
        return $this->hasMany(UbigeoProvince::class, 'department_id');
    }

    public function districts(): HasMany
    {
        return $this->hasMany(UbigeoDistrict::class, 'department_id');
    }
}
