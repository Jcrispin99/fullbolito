<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ImportExport\ImportJob
 */
final class ImportJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $job = $this->resource;

        return [
            'id' => $job->id,
            'uuid' => $job->uuid,
            'resource' => $job->getAttribute('resource'),
            'template_id' => $job->template_id,
            'user_id' => $job->user_id,
            'original_filename' => $job->original_filename,
            'file_size' => $job->file_size,
            'format' => $job->format,
            'delimiter' => $job->delimiter,
            'encoding' => $job->encoding,
            'mapping' => $job->mapping,
            'mode' => $job->mode,
            'unique_key' => $job->unique_key,
            'options' => $job->options,
            'status' => $job->status,
            'total_rows' => $job->total_rows,
            'processed_rows' => $job->processed_rows,
            'imported_rows' => $job->imported_rows,
            'failed_rows' => $job->failed_rows,
            'has_errors_file' => ! empty($job->errors_file_path),
            'error_summary' => $job->error_summary,
            'started_at' => $job->started_at?->toIso8601String(),
            'finished_at' => $job->finished_at?->toIso8601String(),
            'created_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $job->updated_at?->toIso8601String(),
            'preview' => $job->getAttribute('preview'),
            'headers' => $job->getAttribute('headers'),
            'suggested_mapping' => $job->getAttribute('suggested_mapping'),
        ];
    }
}
