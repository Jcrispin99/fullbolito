<?php

declare(strict_types=1);

namespace App\Models\ImportExport;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ImportTemplate extends Model
{
    use HasFactory;

    protected $table = 'import_export_templates';

    protected $fillable = [
        'name',
        'resource',
        'direction',
        'columns',
        'default_options',
        'owner_user_id',
        'is_shared',
    ];

    protected $casts = [
        'columns' => 'array',
        'default_options' => 'array',
        'is_shared' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
