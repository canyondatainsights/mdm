<?php

namespace App\Services\Kb;

use App\Models\Chunk;
use App\Models\Source;
use App\Models\WikiPage;
use App\Services\Embeddings\Embedder;
use Illuminate\Support\Facades\DB;
use Pgvector\Laravel\Vector;

class Ingestor
{
    public function __construct(
        private Embedder $embedder,
        private DocumentParser $parser,
        private Chunker $chunker,
    ) {}

    /**
     * Ingest the whole KB (wiki/ + raw/). Returns per-file stats.
     *
     * @return array<int, array{path:string, status:string, chunks:int}>
     */
    public function ingestAll(?callable $progress = null): array
    {
        $root = rtrim(config('mdm.kb_path'), '/');
        $results = [];

        foreach ($this->files($root.'/wiki', ['md', 'markdown']) as $abs) {
            $results[] = $r = $this->ingestFile($abs, 'wiki', $root);
            $progress && $progress($r);
        }

        foreach ($this->files($root.'/raw', ['md', 'markdown', 'txt', 'pdf']) as $abs) {
            $results[] = $r = $this->ingestFile($abs, 'raw', $root);
            $progress && $progress($r);
        }

        return $results;
    }

    /**
     * @param  array<string,mixed>  $overrides  Explicit metadata (vendor/product/version/…) that wins over derivation.
     * @return array{path:string, status:string, chunks:int}
     */
    public function ingestFile(string $abs, string $kind, ?string $root = null, array $overrides = []): array
    {
        $root = rtrim($root ?? config('mdm.kb_path'), '/');
        $rel = ltrim(str_replace($root, '', $abs), '/');

        $parsed = $this->parser->parse($abs);
        if ($parsed === null) {
            return ['path' => $rel, 'status' => 'unsupported', 'chunks' => 0];
        }

        $hash = md5($parsed['body']);
        $meta = Metadata::resolve($rel, $parsed['front_matter'], $parsed['body'], $overrides);
        $section = $this->section($rel);

        $wikiPageId = null;
        $sourceId = null;

        if ($kind === 'wiki') {
            $page = WikiPage::firstOrNew(['path' => $rel]);
            if ($page->exists && $page->content_hash === $hash) {
                return ['path' => $rel, 'status' => 'unchanged', 'chunks' => 0];
            }
            $page->fill([
                'title' => $meta['title'] ?? $parsed['title'],
                'section' => $section,
                'mdm_vendor' => $meta['mdm_vendor'],
                'data_platform' => $meta['data_platform'],
                'financial_model' => $meta['financial_model'],
                'domain' => $meta['domain'],
                'scope' => $meta['scope'],
                'product' => $meta['product'],
                'product_version' => $meta['product_version'],
                'page_updated_at' => $meta['page_updated_at'],
                'content_hash' => $hash,
            ])->save();
            $wikiPageId = $page->id;
        } else {
            $source = Source::firstOrNew(['path' => $rel]);
            $source->fill([
                'title' => $meta['title'] ?? $parsed['title'],
                'doc_type' => $parsed['doc_type'],
                'pages' => $parsed['pages'],
                'mdm_vendor' => $meta['mdm_vendor'],
                'data_platform' => $meta['data_platform'],
                'financial_model' => $meta['financial_model'],
                'domain' => $meta['domain'],
                'scope' => $meta['scope'],
                'product' => $meta['product'],
                'product_version' => $meta['product_version'],
                'extension' => $meta['extension'] ?? null,
                // Held out of retrieval until it has a usable isolation signal: an MDM vendor+product,
                // OR a financial data model (ISDA CDM/FpML), OR a platform, OR a specific neutral
                // subject (a non-"general" domain, e.g. data-privacy) — those are intentionally shared.
                'needs_metadata' => ! (($meta['mdm_vendor'] && $meta['product']) || ! empty($meta['financial_model']) || ! empty($meta['data_platform']) || (! empty($meta['domain']) && $meta['domain'] !== 'general')),
                'content_hash' => $hash,
                'superseded' => false,
            ]);
            $source->save();
            $sourceId = $source->id;

            // Dedupe — keep this (latest) copy; supersede older sources that are either the
            // exact same content, or the same filename of the same product/vendor (a re-upload).
            $base = '%/'.strtolower(basename($rel));
            Source::where('id', '!=', $source->id)
                ->where('superseded', false)
                ->where(function ($w) use ($hash, $base, $meta) {
                    $w->where('content_hash', $hash);
                    if (! empty($meta['mdm_vendor']) && ! empty($meta['product'])) {
                        $w->orWhere(fn ($w2) => $w2->whereRaw('lower(path) like ?', [$base])
                            ->where('mdm_vendor', $meta['mdm_vendor'])
                            ->where('product', $meta['product']));
                    }
                })
                ->update(['superseded' => true]);
        }

        $chunks = $this->chunker->chunk($parsed['body']);
        if (empty($chunks)) {
            return ['path' => $rel, 'status' => 'empty', 'chunks' => 0];
        }

        $vectors = $this->embedder->embed(array_column($chunks, 'content'));

        DB::transaction(function () use ($rel, $chunks, $vectors, $meta, $kind, $wikiPageId, $sourceId) {
            // Replace strategy: clear prior chunks for this file, then insert fresh.
            Chunk::where('source_path', $rel)->delete();

            foreach ($chunks as $i => $c) {
                Chunk::create([
                    'source_kind' => $kind,
                    'source_path' => $rel,
                    'wiki_page_id' => $wikiPageId,
                    'source_id' => $sourceId,
                    'anchor' => $c['anchor'],
                    'chunk_index' => $i,
                    'content' => $c['content'],
                    'token_count' => $c['token_count'],
                    'content_hash' => md5($c['content']),
                    'mdm_vendor' => $meta['mdm_vendor'],
                    'data_platform' => $meta['data_platform'],
                    'financial_model' => $meta['financial_model'],
                    'domain' => $meta['domain'],
                    'scope' => $meta['scope'],
                    'product' => $meta['product'],
                    'product_version' => $meta['product_version'],
                    'extension' => $meta['extension'] ?? null,
                    'embedding' => new Vector($vectors[$i]),
                ]);
            }
        });

        return ['path' => $rel, 'status' => 'indexed', 'chunks' => count($chunks)];
    }

    /** @return string[] absolute file paths */
    private function files(string $dir, array $exts): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $exts, true)) {
                $out[] = $file->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    private function section(string $rel): ?string
    {
        // wiki/02-informatica-mdm/customer-360.md => 02-informatica-mdm
        $parts = explode('/', $rel);

        return $parts[1] ?? null;
    }
}
