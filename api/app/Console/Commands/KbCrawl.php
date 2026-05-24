<?php

namespace App\Console\Commands;

use App\Jobs\IngestUrlSource;
use App\Models\AuditLog;
use App\Models\Source;
use App\Services\Kb\SitemapFetcher;
use App\Services\Kb\UploadTagger;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Crawl an official vendor documentation site (config 'mdm.crawlers.<vendor>'): discover pages from
 * the sitemap, keep only the curated sections, classify each by URL path (no LLM), and queue them
 * for ingestion as approved, platform-tagged URL sources. Re-runnable — content_hash dedup skips
 * unchanged pages. e.g. `kb:crawl databricks --dry-run`, `kb:crawl databricks --limit=50`.
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

    public function handle(SitemapFetcher $sitemaps, UploadTagger $tagger): int
    {
        $vendor = strtolower($this->argument('vendor'));
        $profile = config("mdm.crawlers.$vendor");
        if (! $profile) {
            $this->error("No crawler profile for '{$vendor}' (see config mdm.crawlers).");

            return self::FAILURE;
        }

        $platform = $profile['platform'] ?? $vendor;
        $sections = $profile['sections'] ?? [];
        $exclude = $profile['exclude'] ?? [];
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('sections'))));
        $limit = (int) $this->option('limit');
        $sleep = max(0, (int) $this->option('sleep'));

        // 1. Discover.
        $urls = [];
        foreach ((array) ($profile['sitemaps'] ?? []) as $sm) {
            $urls = array_merge($urls, $sitemaps->urls($sm));
        }
        $urls = array_values(array_unique($urls));
        $this->info('Discovered '.count($urls).' URLs from '.count($profile['sitemaps'] ?? []).' sitemap(s).');
        if (! $urls) {
            $this->warn('No URLs — check the sitemap URL(s) in the profile.');

            return self::FAILURE;
        }

        // 2. Filter + classify by path.
        $matched = [];
        foreach ($urls as $u) {
            $lower = strtolower($u);
            foreach ($exclude as $ex) {
                if (str_contains($lower, strtolower($ex))) {
                    continue 2;
                }
            }
            $path = strtolower(parse_url($u, PHP_URL_PATH) ?? '');
            $segments = array_filter(explode('/', $path));
            $section = $product = null;
            $domain = 'general';
            foreach ($sections as $label => $def) {
                // Simple form  'segment' => [product, domain]  → exact path-segment match (Databricks).
                // Rich form    'label' => ['product'=>,'domain'=>,'match'=>[substrings]] → path substring match.
                if (array_is_list($def)) {
                    if (in_array($label, $segments, true)) {
                        [$product, $domain] = $def;
                        $section = $label;
                        break;
                    }

                    continue;
                }
                foreach ($def['match'] ?? [$label] as $pat) {
                    if (str_contains($path, $pat)) {
                        $product = $def['product'] ?? null;
                        $domain = $def['domain'] ?? 'general';
                        $section = $label;
                        break 2;
                    }
                }
            }
            if ($section === null || ($only && ! in_array($section, $only, true))) {
                continue;
            }
            $matched[$u] = ['section' => $section, 'product' => $product, 'domain' => $domain];
        }

        $bySection = [];
        foreach ($matched as $m) {
            $bySection[$m['section']] = ($bySection[$m['section']] ?? 0) + 1;
        }
        ksort($bySection);
        foreach ($bySection as $s => $c) {
            $this->line(sprintf('  %-26s %d', $s, $c));
        }
        $this->info('Matched '.count($matched).' pages across '.count($bySection).' section(s).');

        if ($this->option('dry-run')) {
            $this->info('[dry-run] nothing ingested.');

            return self::SUCCESS;
        }

        // 3. Queue ingestion (platform-tagged, auto-approved; dedup handled by the Ingestor).
        $root = rtrim(config('mdm.kb_path'), '/');
        $queued = 0;
        foreach ($matched as $url => $m) {
            if ($limit && $queued >= $limit) {
                break;
            }
            $overrides = $tagger->buildOverrides([
                'data_platform' => $platform,
                'product' => $m['product'],
                'domain' => $m['domain'],
                'scope' => 'vendor-specific',
            ]);

            $dir = $tagger->destDir($root, $overrides, $vendor);
            $slug = Str::slug(trim((string) parse_url($url, PHP_URL_PATH), '/')) ?: ('p-'.substr(md5($url), 0, 10));
            $rel = ltrim(str_replace($root, '', $dir.'/'.$slug.'.md'), '/');

            Source::markQueued($rel, $overrides)->update(['approved' => true, 'doc_type' => 'URL', 'owner' => $url]);
            $job = IngestUrlSource::dispatch($url, $rel, $root, $overrides, null);
            if ($sleep) {
                $job->delay(now()->addSeconds($queued * $sleep));
            }
            $queued++;
        }

        AuditLog::record('kb.crawled', ['vendor' => $vendor, 'platform' => $platform, 'queued' => $queued, 'matched' => count($matched)]);
        $this->info("Queued {$queued} page(s) for ingestion — run the queue worker. Re-runs skip unchanged pages.");

        return self::SUCCESS;
    }
}
