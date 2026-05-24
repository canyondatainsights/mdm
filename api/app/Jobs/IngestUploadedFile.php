<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Source;
use App\Services\Kb\Ingestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Parses, chunks, embeds, and indexes one uploaded KB file off the request thread,
 * so bulk uploads of large PDFs don't time out the HTTP request.
 */
class IngestUploadedFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    /** @param  array<string,mixed>  $overrides */
    public function __construct(
        public string $absPath,
        public string $root,
        public array $overrides = [],
        public ?int $uploadedBy = null,
    ) {}

    public function handle(Ingestor $ingestor): void
    {
        $rel = ltrim(str_replace(rtrim($this->root, '/'), '', $this->absPath), '/');

        if (! is_file($this->absPath)) {
            Source::where('path', $rel)->update(['ingest_status' => 'failed']);

            return;
        }

        Source::where('path', $rel)->update(['ingest_status' => 'processing']);

        try {
            $result = $ingestor->ingestFile($this->absPath, 'raw', $this->root, $this->overrides);
        } catch (\Throwable $e) {
            Source::where('path', $rel)->update(['ingest_status' => 'failed']);
            throw $e;
        }

        // Attribute the source to the uploader and mark it ready/available.
        $source = Source::where('path', $result['path'])->first();
        if ($source) {
            $source->forceFill([
                'uploaded_by' => $this->uploadedBy ?? $source->uploaded_by,
                'ingest_status' => 'ready',
            ])->save();
        }

        AuditLog::record(
            'source.ingested',
            [
                'path' => $result['path'],
                'status' => $result['status'],
                'chunks' => $result['chunks'],
                'product' => $this->overrides['product'] ?? null,
                'product_version' => $this->overrides['product_version'] ?? null,
            ],
            'Source',
            (string) ($source->id ?? ''),
        );
    }
}
