<?php

declare(strict_types=1);

namespace App\ImportExport\Runners;

use App\ImportExport\Contracts\ResourceSchema;
use Illuminate\Database\Eloquent\Model;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportRunner
{
    /**
     * Stream a download for the given schema, columns and filters.
     *
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $filters
     */
    public function stream(
        ResourceSchema $schema,
        array $columns,
        array $filters = [],
        ?string $filename = null,
        string $format = 'csv',
        string $delimiter = ',',
    ): StreamedResponse {
        $format = in_array($format, ['csv', 'xlsx'], true) ? $format : 'csv';
        $columns = $this->validateColumns($schema, $columns);
        $filename ??= $schema->key().'-'.date('Ymd-His').'.'.$format;
        if (! str_ends_with($filename, '.'.$format)) {
            $filename .= '.'.$format;
        }

        $contentType = $format === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=UTF-8';

        $headers = [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache',
        ];

        $labels = $this->columnLabels($schema, $columns);

        $response = new StreamedResponse(function () use ($schema, $columns, $filters, $labels, $format, $delimiter): void {
            $writer = $this->makeWriter($format, $delimiter);
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues($labels));

            $query = $schema->query();
            $query = $schema->applyFilters($query, $filters);

            $query->chunk(500, function ($models) use ($writer, $schema, $columns): void {
                foreach ($models as $model) {
                    /** @var Model $model */
                    $values = [];
                    foreach ($columns as $column) {
                        $values[] = $schema->exportValue($model, $column);
                    }
                    $writer->addRow(Row::fromValues($values));
                }
            });

            $writer->close();
        }, 200, $headers);

        return $response;
    }

    private function makeWriter(string $format, string $delimiter): WriterInterface
    {
        if ($format === 'xlsx') {
            return new XlsxWriter();
        }

        $options = new CsvOptions(
            FIELD_DELIMITER: $delimiter,
            SHOULD_ADD_BOM: true,
        );

        return new CsvWriter($options);
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function validateColumns(ResourceSchema $schema, array $columns): array
    {
        $allowed = [];
        foreach ($schema->fields() as $field) {
            if ($field->exportable) {
                $allowed[$field->key] = $field->label;
            }
        }

        $valid = [];
        foreach ($columns as $column) {
            if (isset($allowed[$column])) {
                $valid[] = $column;
            }
        }

        if ($valid === []) {
            $valid = array_keys($allowed);
        }

        return $valid;
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function columnLabels(ResourceSchema $schema, array $columns): array
    {
        $labels = [];
        foreach ($schema->fields() as $field) {
            $labels[$field->key] = $field->label;
        }

        return array_map(fn (string $col): string => $labels[$col] ?? $col, $columns);
    }
}
