<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chunk;
use App\Models\Source;
use App\Models\WikiPage;
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

    /** Source detail for the inspector: metadata + excerpt + related. */
    public function show(string $path)
    {
        $rel = ltrim($path, '/');
        $page = WikiPage::where('path', $rel)->first();

        $chunks = Chunk::where('source_path', $rel)->orderBy('chunk_index')->limit(3)->get();

        return [
            'path' => $rel,
            'title' => $page?->title ?? basename($rel),
            'doc_type' => 'MD',
            'mdm_vendor' => $page?->mdm_vendor,
            'data_platform' => $page?->data_platform,
            'domain' => $page?->domain,
            'scope' => $page?->scope,
            'updated' => optional($page?->page_updated_at)->toDateString(),
            'tags' => array_values(array_filter([$page?->mdm_vendor, $page?->data_platform, $page?->domain])),
            'excerpt' => $chunks->map(fn ($c) => ['anchor' => $c->anchor, 'text' => $c->content])->values(),
            'related' => WikiPage::where('section', $page?->section)
                ->where('path', '!=', $rel)->limit(5)->get(['title', 'path']),
        ];
    }
}
