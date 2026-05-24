<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Chunk;
use App\Models\Source;
use App\Services\Kb\Classifier;
use App\Services\Kb\DocumentParser;
use App\Services\Taxonomy\Taxonomy;
use Illuminate\Console\Command;

/**
 * Re-classify every KB source (vendor-agnostic) with the LLM classifier and re-tag the source + its
 * chunks with precise product / primary domain / industry extension. Fixes cross-domain and
 * vertical-edition contamination (e.g. Supplier/Insurance bleeding into a Customer-360 answer) and
 * surfaces mis-classified vendor content (e.g. Databricks). Use --dry-run to preview, --revert to undo.
 */
class KbReclassify extends Command
{
    protected $signature = 'kb:reclassify
        {--dry-run : preview the tag changes without writing}
        {--only= : only sources whose path contains this substring}
        {--sleep=3 : seconds to pause between LLM calls (provider rate limits)}
        {--revert : restore tags from the most recent reclassify run}';

    protected $description = 'Re-classify KB sources + chunks (product/domain/extension) via the LLM classifier.';

    /** Fields the reclassifier owns. */
    private const FIELDS = ['mdm_vendor', 'product', 'domain', 'extension', 'financial_model'];

    public function handle(Classifier $classifier, DocumentParser $parser): int
    {
        if ($this->option('revert')) {
            return $this->revert();
        }

        $query = Source::query()->orderBy('path');
        if ($only = $this->option('only')) {
            $query->where('path', 'like', '%'.$only.'%');
        }
        $sources = $query->get();
        if ($sources->isEmpty()) {
            $this->warn('No sources matched.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $sleep = max(0, (int) $this->option('sleep'));
        $root = rtrim(config('mdm.kb_path'), '/');
        $snapshot = [];
        $changed = 0;
        $first = true;

        foreach ($sources as $s) {
            $abs = $root.'/'.$s->path;
            // Keep the excerpt small — classification needs only the title region, and a smaller
            // payload stays well under the provider's input-tokens-per-minute limit.
            $excerpt = is_file($abs) ? $parser->excerpt($abs, 4000) : '';
            if ($excerpt === '') {
                $this->warn("skip (no readable text): {$s->path}");

                continue;
            }

            if (! $first && $sleep > 0) {
                sleep($sleep); // pace LLM calls to respect rate limits
            }
            $first = false;

            try {
                $r = $this->classifyWithRetry($classifier, basename($s->path), $excerpt);
            } catch (\Throwable $e) {
                $this->warn("classify failed {$s->path}: ".$e->getMessage());

                continue;
            }

            // Keep existing vendor/product/domain when the classifier is unsure (null); extension is
            // authoritative (null = core) so stale verticals get cleared.
            $new = [
                'mdm_vendor' => $r['mdm_vendor'] ?? $s->mdm_vendor,
                'product' => $r['product'] ?? $s->product,
                'domain' => $r['domain'] ?? $s->domain,
                'extension' => $r['extension'],
                'financial_model' => $r['financial_model'] ?? $s->financial_model,
            ];
            // A product is vendor-scoped — if the doc has no vendor, a stale product tag is invalid
            // (e.g. a CDM standard wrongly left as "STEP"). Clear it.
            if (empty($new['mdm_vendor'])) {
                $new['product'] = null;
            }

            $diff = [];
            foreach (self::FIELDS as $k) {
                if (($s->{$k} ?? null) !== $new[$k]) {
                    $diff[] = "{$k} ".($s->{$k} ?? 'null').'→'.($new[$k] ?? 'null');
                }
            }
            if (! $diff) {
                continue;
            }

            $changed++;
            $this->line('• '.basename($s->path).':  '.implode('   ', $diff));
            if ($dry) {
                continue;
            }

            $snapshot[$s->path] = ['mdm_vendor' => $s->mdm_vendor, 'product' => $s->product, 'domain' => $s->domain, 'extension' => $s->extension, 'financial_model' => $s->financial_model];
            Source::where('path', $s->path)->update($new);
            Chunk::where('source_path', $s->path)->update($new);
        }

        if ($dry) {
            $this->info("[dry-run] {$changed} source(s) would change. Re-run without --dry-run to apply.");

            return self::SUCCESS;
        }

        if ($snapshot) {
            AuditLog::record('kb.reclassified', ['count' => count($snapshot), 'snapshot' => $snapshot]);
            Taxonomy::flush();
        }
        $this->info("Reclassified {$changed} source(s) + their chunks. Use `kb:reclassify --revert` to undo.");

        return self::SUCCESS;
    }

    /** Classify with backoff on provider rate-limit errors (parses "retry after N"). */
    private function classifyWithRetry(Classifier $classifier, string $name, string $excerpt): array
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $classifier->classify($name, $excerpt);
            } catch (\Throwable $e) {
                $isRate = str_contains(strtolower($e->getMessage()), 'rate limit');
                if (! $isRate || $attempt > 6) {
                    throw $e;
                }
                $wait = (preg_match('/retry after (\d+)/i', $e->getMessage(), $m) ? (int) $m[1] : 8) + 1;
                $this->warn("  rate limited; waiting {$wait}s (attempt {$attempt})…");
                sleep($wait);
            }
        }
    }

    private function revert(): int
    {
        $log = AuditLog::where('action', 'kb.reclassified')->latest('created_at')->first();
        $snapshot = $log?->meta['snapshot'] ?? null;
        if (! $snapshot) {
            $this->error('No reclassify snapshot found to revert.');

            return self::FAILURE;
        }

        $n = 0;
        foreach ($snapshot as $path => $tags) {
            Source::where('path', $path)->update($tags);
            Chunk::where('source_path', $path)->update($tags);
            $n++;
        }
        AuditLog::record('kb.reclassify_reverted', ['count' => $n, 'from_audit' => $log->id]);
        Taxonomy::flush();
        $this->info("Reverted {$n} source(s) + their chunks to pre-reclassify tags.");

        return self::SUCCESS;
    }
}
