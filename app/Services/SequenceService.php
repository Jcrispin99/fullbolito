<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Journal;

final class SequenceService
{
    /**
     * @return array{serie: string, correlative: string}
     */
    public static function getNextParts(int $journalId): array
    {
        $journal = Journal::with('sequence')->findOrFail($journalId);

        if (! $journal->sequence) {
            return ['serie' => 'F001', 'correlative' => '0000001'];
        }

        return $journal->sequence->getNextParts();
    }
}
