<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Source;
use App\Services\Kb\Ingestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fetches a user-submitted reference URL, extracts readable text into a tagged
 * Markdown file under kb/raw/, then runs it through the normal Ingestor. Lets a URL
 * be a first-class KB source alongside uploaded PDFs/MD/TXT.
 */
class IngestUrlSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    /** @param  array<string,mixed>  $overrides */
    public function __construct(
        public string $url,
        public string $targetRel,   // deterministic kb-relative path (pollable for progress)
        public string $root,
        public array $overrides = [],
        public ?int $uploadedBy = null,
    ) {}

    public function handle(Ingestor $ingestor): void
    {
        Source::where('path', $this->targetRel)->update(['ingest_status' => 'processing']);

        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'MDM-KnowledgeHub/1.0 (+reference ingestion)'])
                ->get($this->url);

            if (! $response->successful()) {
                Source::where('path', $this->targetRel)->update(['ingest_status' => 'failed']);
                AuditLog::record('source.url_failed', ['url' => $this->url, 'status' => $response->status()]);

                return;
            }

            $html = $response->body();
            $title = $this->extractTitle($html) ?? (parse_url($this->url, PHP_URL_HOST) ?: 'Reference');
            $text = $this->htmlToText($html);

            $abs = rtrim($this->root, '/').'/'.$this->targetRel;
            @mkdir(dirname($abs), 0775, true);
            file_put_contents($abs, $this->buildMarkdown($title, $text));

            $result = $ingestor->ingestFile($abs, 'raw', $this->root, $this->overrides);

            $source = Source::where('path', $result['path'])->first();
            if ($source) {
                $source->forceFill([
                    'doc_type' => 'URL',
                    'owner' => $this->url,
                    'title' => $title,
                    'uploaded_by' => $this->uploadedBy ?? $source->uploaded_by,
                    'ingest_status' => 'ready',
                ])->save();
            }

            AuditLog::record(
                'source.ingested',
                ['url' => $this->url, 'path' => $result['path'], 'status' => $result['status'], 'chunks' => $result['chunks']],
                'Source',
                (string) ($source->id ?? ''),
            );
        } catch (\Throwable $e) {
            Source::where('path', $this->targetRel)->update(['ingest_status' => 'failed']);
            throw $e;
        }
    }

    private function buildMarkdown(string $title, string $text): string
    {
        $fmKeys = [
            'mdm_vendor' => 'vendor', 'data_platform' => 'platform', 'domain' => 'domain',
            'scope' => 'scope', 'product' => 'product', 'product_version' => 'version',
        ];
        $front = "---\nsource_url: \"{$this->url}\"\ntitle: \"".str_replace('"', '', $title)."\"\n";
        foreach ($fmKeys as $key => $fm) {
            if (! empty($this->overrides[$key])) {
                $front .= "{$fm}: \"".str_replace('"', '', (string) $this->overrides[$key])."\"\n";
            }
        }
        $front .= "---\n\n";

        return $front."# {$title}\n\nSource: {$this->url}\n\n".$text."\n";
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $t = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $t !== '' ? $t : null;
        }

        return null;
    }

    private function htmlToText(string $html): string
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
