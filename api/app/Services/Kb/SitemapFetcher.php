<?php

namespace App\Services\Kb;

use Illuminate\Support\Facades\Http;

/**
 * Fetches XML sitemaps and returns the page URLs. Handles both a flat <urlset> and a
 * <sitemapindex> (recurses one level into child sitemaps). Parsing is regex-based on <loc>
 * so it's namespace-agnostic and robust across sitemap variants.
 */
class SitemapFetcher
{
    /** @return array<int,string> de-duplicated page URLs (child .xml sitemaps are followed) */
    public function urls(string $sitemapUrl, int $depth = 0): array
    {
        $pages = [];
        foreach ($this->locs($sitemapUrl) as $loc) {
            if ($depth < 2 && preg_match('#\.xml(\.gz)?$#i', $loc)) {
                $pages = array_merge($pages, $this->urls($loc, $depth + 1));
            } else {
                $pages[] = $loc;
            }
        }

        return array_values(array_unique($pages));
    }

    /** Extract <loc> values from one sitemap document. @return array<int,string> */
    private function locs(string $url): array
    {
        try {
            $r = Http::timeout(60)
                ->withHeaders(['User-Agent' => 'MDM-KnowledgeHub/1.0 (+docs crawler)'])
                ->get($url);
            if (! $r->successful()) {
                return [];
            }
            if (preg_match_all('#<loc>\s*(.*?)\s*</loc>#is', $r->body(), $m)) {
                return array_map(fn ($u) => html_entity_decode(trim($u), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $m[1]);
            }
        } catch (\Throwable) {
            // unreachable / invalid sitemap → no URLs
        }

        return [];
    }
}
