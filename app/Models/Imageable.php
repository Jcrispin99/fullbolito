<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Imageable extends Model
{
    protected $table = 'images';

    protected $fillable = [
        'imageable_id',
        'imageable_type',
        'path',
        'size',
    ];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
