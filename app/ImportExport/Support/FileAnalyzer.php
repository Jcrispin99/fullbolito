<?php

declare(strict_types=1);

namespace App\ImportExport\Support;

use OpenSpout\Reader\CSV\Options as CsvReaderOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

final class FileAnalyzer
{
    /**
     * Detect CSV delimiter by sampling first non-empty line.
     */
    public function detectCsvDelimiter(string $absolutePath): string
    {
        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return ',';
        }

        $line = '';
        while (! feof($handle)) {
            $line = (string) fgets($handle);
            if (trim($line) !== '') {
                break;
            }
        }
        fclose($handle);

        $candidates = [',', ';', "\t", '|'];
        $best = ',';
        $bestCount = -1;
        foreach ($candidates as $candidate) {
            $count = substr_count($line, $candidate);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Open the right OpenSpout reader for the given format.
     */
    public function makeReader(string $format, ?string $delimiter = null): ReaderInterface
    {
        if ($format === 'xlsx') {
            return new XlsxReader();
        }

        $options = new CsvReaderOptions(
            FIELD_DELIMITER: $delimiter !== null && $delimiter !== '' ? $delimiter : ',',
        );

        return new CsvReader($options);
    }

    /**
     * Read headers + first N preview rows + count total rows.
     *
     * @return array{headers: array<int, string>, preview: array<int, array<int, string>>, total_rows: int}
     */
    public function analyze(string $absolutePath, string $format, ?string $delimiter, int $previewLimit = 10): array
    {
        if (! is_readable($absolutePath)) {
            throw new RuntimeException("File [{$absolutePath}] is not readable.");
        }

        $reader = $this->makeReader($format, $delimiter);
        $reader->open($absolutePath);

        $headers = [];
        $preview = [];
        $totalRows = 0;
        $headerCaptured = false;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                /** @var \OpenSpout\Common\Entity\Row $row */
                $cells = array_map(
                    static fn ($v): string => is_scalar($v) || $v === null ? (string) $v : (string) $v,
                    $row->toArray(),
                );

                if (! $headerCaptured) {
                    $headers = array_map('trim', $cells);
                    $headerCaptured = true;
                    continue;
                }

                if (count($preview) < $previewLimit) {
                    $preview[] = $cells;
                }
                $totalRows++;
            }
            // Only first sheet
            break;
        }

        $reader->close();

        return [
            'headers' => $headers,
            'preview' => $preview,
            'total_rows' => $totalRows,
        ];
    }
}
