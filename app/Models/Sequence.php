<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Sequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'prefix',
        'sequence_size',
        'step',
        'next_number',
    ];

    /**
     * @return array{serie: string, correlative: string}
     */
    public function getNextParts(): array
    {
        $nextNumber = $this->next_number;
        $this->increment('next_number', $this->step);

        return [
            'serie' => $this->prefix ?? 'F001',
            'correlative' => str_pad((string) $nextNumber, $this->sequence_size, '0', STR_PAD_LEFT),
        ];
    }
}
