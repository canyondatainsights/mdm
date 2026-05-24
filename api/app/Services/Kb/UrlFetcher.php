<?php

namespace App\Services\Kb;

use Illuminate\Support\Facades\Http;

/**
 * Fetches a reference URL and extracts its readable text + title. Shared by IngestUrlSource
 * (full ingestion) and the pre-ingest classifier (excerpt), so a URL is handled like a file.
 */
class UrlFetcher
{
    /**
     * @return array{title:string, text:string}
     *
     * @throws \RuntimeException on a non-2xx response
     */
    public function fetch(string $url, bool $withImages = false): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['User-Agent' => 'MDM-KnowledgeHub/1.0 (+reference ingestion)'])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch URL (HTTP {$response->status()}).");
        }

        $html = $response->body();

        $result = [
            'title' => $this->extractTitle($html) ?? (parse_url($url, PHP_URL_HOST) ?: 'Reference'),
            'text' => $this->htmlToText($html),
        ];
        if ($withImages) {
            $result['images'] = $this->images($html, $url);
        }

        return $result;
    }

    /**
     * Candidate content images (absolute URL + alt) from the page, skipping icons/logos/pixels.
     *
     * @return array<int, array{src:string, alt:string}>
     */
    public function images(string $html, string $baseUrl, int $max = 12): array
    {
        // Drop chrome so we don't pull header/footer logos and nav sprites.
        $clean = preg_replace('#<(script|style|head|noscript|nav|footer|header)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        preg_match_all('#<(?:img|source)\b[^>]*>#i', $clean, $tags);

        $out = [];
        $seen = [];
        foreach ($tags[0] as $tag) {
            $src = null;
            if (preg_match('#\s(?:data-src|src)\s*=\s*["\']([^"\']+)["\']#i', $tag, $m)) {
                $src = trim($m[1]);
            } elseif (preg_match('#\ssrcset\s*=\s*["\']([^"\']+)["\']#i', $tag, $m)) {
                $src = trim(explode(',', $m[1])[0]); // "url 1x, url2 2x" → first candidate
            }
            if (! $src) {
                continue;
            }
            $src = preg_replace('#\s+\d+(?:w|x)$#', '', $src); // strip a leaked srcset descriptor
            if ($src === '' || str_starts_with($src, 'data:')) {
                continue;
            }
            $abs = $this->absoluteUrl($src, $baseUrl);
            if (! $abs || preg_match('#(sprite|favicon|logo|icon|avatar|pixel|tracking|/1x1|spacer|emoji)#i', $abs)) {
                continue;
            }
            // Skip images the markup declares as small (likely icons/thumbnails).
            if (preg_match('#\bwidth\s*=\s*["\']?(\d+)#i', $tag, $w) && (int) $w[1] < 80) {
                continue;
            }
            if (isset($seen[$abs])) {
                continue;
            }
            $seen[$abs] = true;
            $alt = preg_match('#\balt\s*=\s*["\']([^"\']*)["\']#i', $tag, $a)
                ? trim(html_entity_decode($a[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
            $out[] = ['src' => $abs, 'alt' => $alt];
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /** Resolve a possibly-relative image URL against the page URL. */
    private function absoluteUrl(string $src, string $base): ?string
    {
        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }
        $p = parse_url($base);
        if (! $p || empty($p['host'])) {
            return null;
        }
        $scheme = $p['scheme'] ?? 'https';
        if (str_starts_with($src, '//')) {
            return $scheme.':'.$src;
        }
        $origin = $scheme.'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
        if (str_starts_with($src, '/')) {
            return $origin.$src;
        }
        $dir = isset($p['path']) ? preg_replace('#/[^/]*$#', '/', $p['path']) : '/';

        return $origin.$dir.$src;
    }

    /** Fetched page text capped for cheap classification (returns '' on fetch failure). */
    public function excerpt(string $url, int $maxChars = 6000): string
    {
        try {
            return mb_substr(trim($this->fetch($url)['text']), 0, $maxChars);
        } catch (\Throwable) {
            return '';
        }
    }

    public function extractTitle(string $html): ?string
    {
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $t = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $t !== '' ? $t : null;
        }

        return null;
    }

    public function htmlToText(string $html): string
    {
        // Drop non-content blocks entirely.
        $html = preg_replace('#<(script|style|head|noscript|svg|nav|footer)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        // Preserve block boundaries as newlines.
        $html = preg_replace('#</(p|div|li|h[1-6]|tr|section|article|br)\s*>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
