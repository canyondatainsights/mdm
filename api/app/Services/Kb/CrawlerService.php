<?php

namespace App\Services\Kb;

use App\Jobs\IngestUrlSource;
use App\Models\AuditLog;
use App\Models\Source;
use Illuminate\Support\Str;

/**
 * Runs a documentation-crawler profile: discover URLs from the sitemap(s), keep only the curated
 * sections, classify each by URL path (no LLM), and queue them as approved, platform-tagged URL
 * sources. Shared by the kb:crawl command and the Filament "Run crawl" admin action.
 */
class CrawlerService
{
    public function __construct(private SitemapFetcher $sitemaps, private UploadTagger $tagger) {}

    /**
     * @param  array{platform?:string,sitemaps?:array,exclude?:array,sections?:array}  $profile
     * @param  array{only?:array,limit?:int,sleep?:int,dryRun?:bool}  $opts
     * @return array{discovered:int, matched:int, queued:int, bySection:array<string,int>}
     */
    public function crawl(string $key, array $profile, array $opts = []): array
    {
        $only = array_filter((array) ($opts['only'] ?? []));
        $limit = (int) ($opts['limit'] ?? 0);
        $sleep = max(0, (int) ($opts['sleep'] ?? 0));
        $dryRun = (bool) ($opts['dryRun'] ?? false);

        $platform = $profile['platform'] ?? $key;
        $sections = $this->normalizeSections($profile['sections'] ?? []);
        $exclude = $profile['exclude'] ?? [];

        // 1. Discover.
        $urls = [];
        foreach ((array) ($profile['sitemaps'] ?? []) as $sm) {
            $urls = array_merge($urls, $this->sitemaps->urls($sm));
        }
        $urls = array_values(array_unique($urls));

        // 2. Filter + classify by path (first matching section wins).
        $matched = [];
        foreach ($urls as $u) {
            $lower = strtolower($u);
            foreach ($exclude as $ex) {
                if ($ex !== '' && str_contains($lower, strtolower($ex))) {
                    continue 2;
                }
            }
            $path = strtolower(parse_url($u, PHP_URL_PATH) ?? '');
            $segments = array_filter(explode('/', $path));
            $hit = null;
            foreach ($sections as $sec) {
                if (! empty($sec['match'])) {
                    foreach ($sec['match'] as $pat) {
                        if ($pat !== '' && str_contains($path, strtolower($pat))) {
                            $hit = $sec;
                            break;
                        }
                    }
                    if ($hit) {
                        break;
                    }
                } elseif (in_array($sec['section'], $segments, true)) {
                    $hit = $sec;
                    break;
                }
            }
            if (! $hit || ($only && ! in_array($hit['section'], $only, true))) {
                continue;
            }
            $matched[$u] = $hit;
        }

        $bySection = [];
        foreach ($matched as $m) {
            $bySection[$m['section']] = ($bySection[$m['section']] ?? 0) + 1;
        }
        ksort($bySection);

        if ($dryRun) {
            return ['discovered' => count($urls), 'matched' => count($matched), 'queued' => 0, 'bySection' => $bySection];
        }

        // 3. Queue ingestion (platform-tagged, auto-approved; content_hash dedup handles re-runs).
        $root = rtrim(config('mdm.kb_path'), '/');
        $queued = 0;
        foreach ($matched as $url => $m) {
            if ($limit && $queued >= $limit) {
                break;
            }
            $overrides = $this->tagger->buildOverrides([
                'data_platform' => $platform,
                'product' => $m['product'],
                'domain' => $m['domain'],
                'scope' => 'vendor-specific',
            ]);
            $dir = $this->tagger->destDir($root, $overrides, $key);
            $slug = Str::slug(trim((string) parse_url($url, PHP_URL_PATH), '/')) ?: ('p-'.substr(md5($url), 0, 10));
            $rel = ltrim(str_replace($root, '', $dir.'/'.$slug.'.md'), '/');

            Source::markQueued($rel, $overrides)->update(['approved' => true, 'doc_type' => 'URL', 'owner' => $url]);
            $job = IngestUrlSource::dispatch($url, $rel, $root, $overrides, null);
            if ($sleep) {
                $job->delay(now()->addSeconds($queued * $sleep));
            }
            $queued++;
        }

        AuditLog::record('kb.crawled', ['crawler' => $key, 'platform' => $platform, 'queued' => $queued, 'matched' => count($matched)]);

        return ['discovered' => count($urls), 'matched' => count($matched), 'queued' => $queued, 'bySection' => $bySection];
    }

    /**
     * Normalize sections from either the config map (label => [product,domain] or
     * label => {product,domain,match[]}) or the DB list ([{section,product,domain,match[]}])
     * into a uniform ordered list of {section, product, domain, match[]}.
     */
    private function normalizeSections(array $sections): array
    {
        $out = [];
        foreach ($sections as $label => $def) {
            if (is_array($def) && isset($def['section'])) {
                $out[] = ['section' => (string) $def['section'], 'product' => $def['product'] ?? null, 'domain' => $def['domain'] ?? 'general', 'match' => array_values($def['match'] ?? [])];
            } elseif (array_is_list($def)) {
                $out[] = ['section' => (string) $label, 'product' => $def[0] ?? null, 'domain' => $def[1] ?? 'general', 'match' => []];
            } else {
                $out[] = ['section' => (string) $label, 'product' => $def['product'] ?? null, 'domain' => $def['domain'] ?? 'general', 'match' => array_values($def['match'] ?? [])];
            }
        }

        return $out;
    }
}
