<?php

namespace App\Console\Commands;

use App\Jobs\IngestUrlSource;
use App\Models\Source;
use Illuminate\Console\Command;

/**
 * Re-fetch already-ingested URL sources (crawled docs + URL uploads) through the current pipeline,
 * regenerating their on-disk markdown + chunks with the latest extraction (structured headings/lists/
 * tables). Bounded to what's already in the KB — it doesn't re-walk a sitemap (use kb:crawl for that).
 *
 *   php artisan kb:refetch-urls --platform=databricks --sleep=1
 */
class KbRefetchUrls extends Command
{
    protected $signature = 'kb:refetch-urls
        {--platform= : only sources tagged this data_platform (e.g. databricks, snowflake)}
        {--limit=0 : cap the number re-queued (0 = all)}
        {--sleep=0 : seconds to stagger queued jobs (politeness to the source site)}';

    protected $description = 'Re-queue existing URL sources for re-fetch + re-ingest with the current extraction.';

    public function handle(): int
    {
        $q = Source::query()->where('doc_type', 'URL')->where('owner', 'like', 'http%')->where('superseded', false);
        if ($platform = $this->option('platform')) {
            $q->where('data_platform', $platform);
        }
        $sources = $q->orderBy('id')->get();

        $root = rtrim(config('mdm.kb_path'), '/');
        $limit = (int) $this->option('limit');
        $sleep = max(0, (int) $this->option('sleep'));
        $dims = ['mdm_vendor', 'data_platform', 'financial_model', 'domain', 'scope', 'product', 'product_version', 'extension'];

        $n = 0;
        foreach ($sources as $s) {
            if ($limit && $n >= $limit) {
                break;
            }
            $overrides = collect($dims)->mapWithKeys(fn ($k) => [$k => $s->{$k}])->filter()->all();
            Source::markQueued($s->path, $overrides)->update(['approved' => true]);
            $job = IngestUrlSource::dispatch($s->owner, $s->path, $root, $overrides, $s->uploaded_by);
            if ($sleep) {
                $job->delay(now()->addSeconds($n * $sleep));
            }
            $n++;
        }

        $this->info("Re-queued {$n} URL source(s) for re-fetch — run the queue worker; chunks refresh as they process.");

        return self::SUCCESS;
    }
}
