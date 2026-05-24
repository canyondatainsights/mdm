<?php

namespace App\Console\Commands;

use App\Models\Crawler;
use App\Services\Kb\CrawlerService;
use Illuminate\Console\Command;

/**
 * Crawl an official vendor documentation site: discover pages from the sitemap, keep only the
 * curated sections, classify each by URL path (no LLM), and queue them for ingestion as approved,
 * platform-tagged URL sources. The profile comes from the DB `crawlers` table (managed in the admin),
 * falling back to config('mdm.crawlers'). e.g. `kb:crawl databricks --dry-run`, `--limit=50`.
 */
class KbCrawl extends Command
{
    protected $signature = 'kb:crawl
        {vendor : crawler profile key (databricks, snowflake, …)}
        {--dry-run : list matched pages per section without ingesting}
        {--limit=0 : cap the number of pages queued (0 = all matched)}
        {--sections= : comma-separated subset of section keys to crawl}
        {--sleep=0 : seconds to stagger between queued jobs (politeness)}';

    protected $description = 'Crawl a vendor doc site (sitemap → path-classify → queue ingestion).';

    public function handle(CrawlerService $service): int
    {
        $key = strtolower($this->argument('vendor'));
        $crawler = Crawler::where('key', $key)->first();
        $profile = $crawler?->toProfile() ?? config("mdm.crawlers.$key");
        if (! $profile) {
            $this->error("No crawler profile for '{$key}' (admin → Crawlers, or config mdm.crawlers).");

            return self::FAILURE;
        }

        $result = $service->crawl($key, $profile, [
            'only' => array_filter(array_map('trim', explode(',', (string) $this->option('sections')))),
            'limit' => (int) $this->option('limit'),
            'sleep' => max(0, (int) $this->option('sleep')),
            'dryRun' => (bool) $this->option('dry-run'),
        ]);

        $this->info("Discovered {$result['discovered']} URLs.");
        foreach ($result['bySection'] as $s => $c) {
            $this->line(sprintf('  %-26s %d', $s, $c));
        }
        $this->info("Matched {$result['matched']} pages across ".count($result['bySection']).' section(s).');
        if ($this->option('dry-run')) {
            $this->info('[dry-run] nothing ingested.');
        } else {
            $this->info("Queued {$result['queued']} page(s) for ingestion — run the queue worker.");
        }

        return self::SUCCESS;
    }
}
