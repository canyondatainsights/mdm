<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * Runs kb:reclassify in the background (it makes one LLM call per source, so it can't run on an HTTP
 * click) and records the run + its output to the audit log so the admin Re-classify page can show the
 * dry-run diff / applied changes. Bounded by --limit so a run stays within the queue worker timeout;
 * full re-classification should be run from the CLI.
 */
class ReclassifyKb implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 590; // stay just under the worker's --timeout=600

    public function __construct(
        public ?string $only = null,
        public int $limit = 50,
        public int $sleep = 3,
        public bool $dryRun = false,
    ) {}

    public function handle(): void
    {
        $params = array_filter([
            '--only' => $this->only ?: null,
            '--limit' => $this->limit ?: null,
            '--sleep' => $this->sleep,
            '--dry-run' => $this->dryRun ?: null,
        ], fn ($v) => $v !== null);

        Artisan::call('kb:reclassify', $params);
        $output = trim(Artisan::output());

        AuditLog::record('kb.reclassify_run', [
            'dry_run' => $this->dryRun,
            'only' => $this->only,
            'limit' => $this->limit,
            'output' => Str::limit($output, 6000),
        ]);
    }
}
