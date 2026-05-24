<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chunk;
use App\Models\Source;
use App\Models\WikiPage;
use App\Services\Kb\SourceTrust;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SourceController extends Controller
{
    /** Knowledge sources list (wiki pages + uploaded raw sources). */
    public function index()
    {
        $wiki = WikiPage::orderBy('path')->get()->map(fn ($p) => [
            'id' => 'wiki:'.$p->id,
            'kind' => 'wiki',
            'title' => $p->title,
            'path' => $p->path,
            'section' => $p->section,
            'doc_type' => 'MD',
            'mdm_vendor' => $p->mdm_vendor,
            'data_platform' => $p->data_platform,
            'domain' => $p->domain,
            'scope' => $p->scope,
            'updated' => optional($p->page_updated_at)->toDateString(),
        ]);

        $raw = Source::where('superseded', false)->orderBy('title')->get()->map(fn ($s) => [
            'id' => 'raw:'.$s->id,
            'kind' => 'raw',
            'title' => $s->title,
            'path' => $s->path,
            'doc_type' => $s->doc_type,
            'mdm_vendor' => $s->mdm_vendor,
            'data_platform' => $s->data_platform,
            'domain' => $s->domain,
            'scope' => $s->scope,
            'product' => $s->product,
            'product_version' => $s->product_version,
            'approved' => $s->approved,
            'needs_metadata' => $s->needs_metadata,
            'ingest_status' => $s->ingest_status,
        ]);

        return ['count' => $wiki->count() + $raw->count(), 'sources' => $wiki->concat($raw)->values()];
    }

    /** Source detail for the inspector: metadata + hierarchy + excerpt + origin URL + trust + related. */
    public function show(string $path, SourceTrust $trust)
    {
        $rel = ltrim($path, '/');
        $page = WikiPage::where('path', $rel)->first();
        $source = $page ? null : Source::where('path', $rel)->first();
        $entity = $page ?? $source;

        $chunks = Chunk::where('source_path', $rel)->orderBy('chunk_index')->limit(3)->get();

        $vendor = $entity?->mdm_vendor;
        $platform = $entity?->data_platform;
        $domain = $entity?->domain;
        $product = $entity?->product;

        $related = $page
            ? WikiPage::where('section', $page->section)->where('path', '!=', $rel)->limit(5)->get(['title', 'path'])
            : ($source
                ? Source::where('superseded', false)->where('path', '!=', $rel)
                    ->where('mdm_vendor', $source->mdm_vendor)->where('data_platform', $source->data_platform)
                    ->limit(5)->get(['title', 'path'])
                : collect());

        return [
            'path' => $rel,
            'title' => $entity?->title ?? basename($rel),
            'doc_type' => $page ? 'MD' : ($source?->doc_type ?? 'DOC'),
            'mdm_vendor' => $vendor,
            'data_platform' => $platform,
            'financial_model' => $entity?->financial_model,
            'domain' => $domain,
            'product' => $product,
            'extension' => $source?->extension,
            'scope' => $entity?->scope,
            'updated' => optional($page?->page_updated_at ?? $source?->created_at)->toDateString(),
            'origin' => $source?->owner, // original URL for crawled/URL sources
            'approved' => $source ? (bool) $source->approved : true,
            'tags' => array_values(array_filter([$vendor, $platform, $domain, $product])),
            'excerpt' => $chunks->map(fn ($c) => ['anchor' => $c->anchor, 'text' => $c->content])->values(),
            'related' => $related,
            'trust' => $source ? $trust->score($source) : null,
        ];
    }
}
