<?php

declare(strict_types=1);

namespace App\ImportExport\Runners;

use App\ImportExport\Contracts\ResourceSchema;
use App\ImportExport\Support\FileAnalyzer;
use App\Models\ImportExport\ImportJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Options as CsvWriterOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use Throwable;

final class ImportRunner
{
    public function __construct(
        private readonly FileAnalyzer $analyzer,
    ) {
    }

    public function run(ImportJob $job, ResourceSchema $schema): ImportJob
    {
        $job->status = 'processing';
        $job->started_at = now();
        $job->processed_rows = 0;
        $job->imported_rows = 0;
        $job->failed_rows = 0;
        $job->errors_file_path = null;
        $job->save();

        $absolutePath = Storage::disk('local')->path($job->file_path);
        $reader = $this->analyzer->makeReader($job->format, $job->delimiter);

        $mapping = is_array($job->mapping) ? $job->mapping : [];
        $mode = $job->mode ?: 'create';
        $uniqueKey = $job->unique_key;
        $options = is_array($job->options) ? $job->options : [];
        $stopOnError = (bool) ($options['stop_on_error'] ?? false);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $chunkSize = max(50, (int) ($options['chunk_size'] ?? 500));

        $errorRows = [];
        $errorSummary = [];
        $headerCells = null;

        $reader->open($absolutePath);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $buffer = [];
                $rowIndex = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    /** @var Row $row */
                    $cells = array_map(
                        static fn ($v): string => is_scalar($v) || $v === null ? (string) $v : (string) $v,
                        $row->toArray(),
                    );

                    if ($headerCells === null) {
                        $headerCells = $cells;
                        continue;
                    }
                    $rowIndex++;

                    $buffer[] = ['index' => $rowIndex, 'cells' => $cells];

                    if (count($buffer) >= $chunkSize) {
                        $this->processChunk(
                            $schema,
                            $headerCells,
                            $mapping,
                            $mode,
                            $uniqueKey,
                            $dryRun,
                            $stopOnError,
                            $buffer,
                            $job,
                            $errorRows,
                            $errorSummary,
                        );
                        $buffer = [];
                    }
                }

                if ($buffer !== []) {
                    $this->processChunk(
                        $schema,
                        $headerCells,
                        $mapping,
                        $mode,
                        $uniqueKey,
                        $dryRun,
                        $stopOnError,
                        $buffer,
                        $job,
                        $errorRows,
                        $errorSummary,
                    );
                }

                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        if ($errorRows !== []) {
            $job->errors_file_path = $this->writeErrorsFile($job, $headerCells ?? [], $errorRows);
        }

        $job->error_summary = $errorSummary !== []
            ? array_slice($errorSummary, 0, 20, true)
            : null;

        $job->finished_at = now();
        if ($dryRun) {
            $job->status = $errorRows === [] ? 'done' : 'partial';
        } else {
            $job->status = match (true) {
                $job->failed_rows > 0 && $job->imported_rows === 0 => 'failed',
                $job->failed_rows > 0 => 'partial',
                default => 'done',
            };
        }

        $job->save();

        return $job;
    }

    /**
     * @param  array<int, string>  $headerCells
     * @param  array<string, string|null>  $mapping  fileHeader => fieldKey|null
     * @param  array<int, array{index: int, cells: array<int, string>}>  $buffer
     * @param  array<int, array{row: int, cells: array<int, string>, error: string}>  $errorRows
     * @param  array<string, int>  $errorSummary
     */
    private function processChunk(
        ResourceSchema $schema,
        array $headerCells,
        array $mapping,
        string $mode,
        ?string $uniqueKey,
        bool $dryRun,
        bool $stopOnError,
        array $buffer,
        ImportJob $job,
        array &$errorRows,
        array &$errorSummary,
    ): void {
        DB::beginTransaction();
        try {
            foreach ($buffer as $entry) {
                $rowNumber = $entry['index'];
                $cells = $entry['cells'];
                $job->processed_rows++;

                $mapped = [];
                foreach ($headerCells as $i => $header) {
                    $fieldKey = $mapping[$header] ?? null;
                    if ($fieldKey === null || $fieldKey === '') {
                        continue;
                    }
                    $mapped[$fieldKey] = $cells[$i] ?? null;
                }

                $errors = $schema->validateRow($mapped, $mode);
                if ($errors !== []) {
                    $msg = implode(' | ', $errors);
                    $errorRows[] = ['row' => $rowNumber, 'cells' => $cells, 'error' => $msg];
                    $errorSummary[$msg] = ($errorSummary[$msg] ?? 0) + 1;
                    $job->failed_rows++;
                    if ($stopOnError) {
                        throw new \RuntimeException("Row {$rowNumber}: {$msg}");
                    }
                    continue;
                }

                if ($dryRun) {
                    $job->imported_rows++;
                    continue;
                }

                try {
                    $schema->persistRow($mapped, $mode, $uniqueKey);
                    $job->imported_rows++;
                } catch (Throwable $e) {
                    $msg = $e->getMessage();
                    $errorRows[] = ['row' => $rowNumber, 'cells' => $cells, 'error' => $msg];
                    $errorSummary[$msg] = ($errorSummary[$msg] ?? 0) + 1;
                    $job->failed_rows++;
                    if ($stopOnError) {
                        throw $e;
                    }
                }
            }

            $job->save();
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $job->save();
            throw $e;
        }
    }

    /**
     * @param  array<int, string>  $headerCells
     * @param  array<int, array{row: int, cells: array<int, string>, error: string}>  $errorRows
     */
    private function writeErrorsFile(ImportJob $job, array $headerCells, array $errorRows): string
    {
        $filename = "imports/{$job->user_id}/errors-{$job->uuid}.csv";
        $absolutePath = Storage::disk('local')->path($filename);
        Storage::disk('local')->put($filename, '');

        $options = new CsvWriterOptions(
            FIELD_DELIMITER: ',',
            SHOULD_ADD_BOM: true,
        );
        $writer = new CsvWriter($options);
        $writer->openToFile($absolutePath);

        $writer->addRow(Row::fromValues([...$headerCells, '_row', '_error']));
        foreach ($errorRows as $entry) {
            $writer->addRow(Row::fromValues([
                ...$entry['cells'],
                $entry['row'],
                $entry['error'],
            ]));
        }
        $writer->close();

        return $filename;
    }
}
