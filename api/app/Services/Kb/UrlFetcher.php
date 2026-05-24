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
    public function fetch(string $url): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['User-Agent' => 'MDM-KnowledgeHub/1.0 (+reference ingestion)'])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch URL (HTTP {$response->status()}).");
        }

        $html = $response->body();

        return [
            'title' => $this->extractTitle($html) ?? (parse_url($url, PHP_URL_HOST) ?: 'Reference'),
            'text' => $this->htmlToText($html),
        ];
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
