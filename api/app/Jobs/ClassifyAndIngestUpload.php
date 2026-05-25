<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Source;
use App\Services\Kb\Classifier;
use App\Services\Kb\DocumentParser;
use App\Services\Kb\DuplicateChecker;
use App\Services\Kb\Ingestor;
use App\Services\Kb\UploadTagger;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Batch import: classify + ingest ONE staged upload off the request thread. This is what makes large
 * PDF libraries importable — the LLM classification that the interactive uploader does synchronously
 * happens here in the queue, one job per file, coordinated by a Bus::batch for progress.
 */
class ClassifyAndIngestUpload implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public string $stagedPath,     // absolute path of the staged temp file
        public string $originalName,
        public string $root,
        public ?int $uploadedBy = null,
        public bool $autoCreateSubjects = false,
    ) {}

    public function handle(DuplicateChecker $dupes, Classifier $classifier, DocumentParser $parser, UploadTagger $tagger, Ingestor $ingestor): void
    {
        if ($this->batch()?->cancelled() || ! is_file($this->stagedPath)) {
            return;
        }
        $batchId = $this->batch()?->id;

        // 1. Skip docs already in the KB (record for the dashboard).
        $dup = $dupes->checkFile($this->stagedPath, $this->originalName);
        if ($dup['duplicate']) {
            AuditLog::record('batch.skipped', ['batch_id' => $batchId, 'file' => $this->originalName, 'by' => $dup['by'], 'existing' => $dup['existing']['path'] ?? null]);
            @unlink($this->stagedPath);

            return;
        }

        // 2. Classify from a cheap excerpt.
        try {
            $c = $classifier->classify($this->originalName, $parser->excerpt($this->stagedPath));
        } catch (\Throwable) {
            $c = [];
        }

        // 3. New subject: auto-create it now (so it can be tagged); otherwise we record the proposal
        //    AFTER ingest (below) with the source path, so a steward can approve + retag those exact files.
        $proposed = ! empty($c['proposed_subject']['value']) ? $c['proposed_subject'] : null;
        if ($proposed && $this->autoCreateSubjects) {
            $tagger->persistNewSubjects(null, [['new_subject' => $proposed]]);
            $c['domain'] = Str::slug((string) $proposed['value']);
        }

        // 4. Build tags → destination → move staged file → ingest.
        $overrides = $tagger->buildOverrides([
            'mdm_vendor' => $c['mdm_vendor'] ?? null, 'data_platform' => $c['data_platform'] ?? null,
            'domain' => $c['domain'] ?? null, 'extension' => $c['extension'] ?? null,
            'financial_model' => $c['financial_model'] ?? null, 'product' => $c['product'] ?? null,
        ]);

        $root = rtrim($this->root, '/');
        $dir = $tagger->destDir($root, $overrides, null);
        @mkdir($dir, 0775, true);
        $safe = Str::slug(pathinfo($this->originalName, PATHINFO_FILENAME)).'.'.strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
        $destAbs = $dir.'/'.$safe;
        @rename($this->stagedPath, $destAbs);
        $rel = ltrim(str_replace($root, '', $destAbs), '/');

        // Steward-run import → auto-approved; needs_metadata is still computed by the Ingestor.
        Source::markQueued($rel, $overrides)->update(['approved' => true, 'ingest_status' => 'processing']);

        try {
            $result = $ingestor->ingestFile($destAbs, 'raw', $root, $overrides);
        } catch (\Throwable $e) {
            Source::where('path', $rel)->update(['ingest_status' => 'failed']);
            throw $e;
        }

        $source = Source::where('path', $result['path'])->first();
        $source?->forceFill(['uploaded_by' => $this->uploadedBy ?? $source->uploaded_by, 'ingest_status' => 'ready'])->save();

        AuditLog::record('batch.ingested', [
            'batch_id' => $batchId, 'file' => $this->originalName, 'path' => $result['path'],
            'chunks' => $result['chunks'], 'domain' => $overrides['domain'] ?? null, 'vendor' => $overrides['mdm_vendor'] ?? null,
        ], 'Source', (string) ($source->id ?? ''));

        // Record an un-created proposed subject (with the ingested path) for steward review + retag.
        if ($proposed && ! $this->autoCreateSubjects) {
            AuditLog::record('batch.proposed_subject', [
                'batch_id' => $batchId, 'file' => $this->originalName, 'path' => $result['path'],
                'value' => Str::slug((string) $proposed['value']), 'label' => $proposed['label'] ?? $proposed['value'],
            ]);
        }
    }

    /** Log the failure (file + reason) so it surfaces in the batch-import log. */
    public function failed(\Throwable $e): void
    {
        AuditLog::record('batch.failed', [
            'batch_id' => $this->batch()?->id,
            'file' => $this->originalName,
            'error' => Str::limit($e->getMessage(), 160),
        ]);
    }
}
