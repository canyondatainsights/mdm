<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chunk;
use App\Models\Source;
use App\Models\WikiPage;
use App\Services\Kb\SourceTrust;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SourceController extends Controller
{
    /**
     * Knowledge sources list (wiki pages + uploaded raw sources), with server-side search, dimension
     * filters, pagination, and an optional group-by-counts mode. Scales as the KB grows — the UI loads
     * a page (or group counts) at a time instead of all ~4k+ rows.
     */
    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 50), 1), 200);
        $groupBy = (string) $request->input('group_by', '');
        $allowedGroup = ['domain', 'mdm_vendor', 'data_platform', 'doc_type'];

        // Project both tables into one shape, then filter/paginate the union as a subquery.
        $wiki = DB::table('wiki_pages')->selectRaw(<<<'SQL'
            ('wiki:' || id) as id, 'wiki' as kind, title, path, section, 'MD' as doc_type,
            mdm_vendor, data_platform, domain, scope,
            cast(null as varchar) as product, cast(null as varchar) as product_version,
            true as approved, false as needs_metadata, 'ready' as ingest_status,
            cast(page_updated_at as date) as updated
        SQL);
        $raw = DB::table('sources')->where('superseded', false)->selectRaw(<<<'SQL'
            ('raw:' || id) as id, 'raw' as kind, title, path, cast(null as varchar) as section, doc_type,
            mdm_vendor, data_platform, domain, scope,
            product, product_version,
            approved, needs_metadata, ingest_status,
            cast(created_at as date) as updated
        SQL);

        $q = DB::query()->fromSub($wiki->unionAll($raw), 's');

        if ($search = trim((string) $request->input('q', ''))) {
            $q->where(fn ($w) => $w->where('title', 'ilike', '%'.$search.'%')->orWhere('path', 'ilike', '%'.$search.'%'));
        }
        foreach (['doc_type' => 'doc_type', 'vendor' => 'mdm_vendor', 'platform' => 'data_platform', 'domain' => 'domain', 'scope' => 'scope'] as $param => $col) {
            $val = $request->input($param);
            if ($val === '—') {
                $q->whereNull($col);   // expand the "unassigned" group
            } elseif ($val !== null && $val !== '') {
                $q->where($col, $val);
            }
        }
        match ($request->input('status')) {
            'needs_metadata' => $q->where('needs_metadata', true),
            'unapproved' => $q->where('approved', false),
            'failed', 'queued', 'processing', 'ready' => $q->where('ingest_status', $request->input('status')),
            default => null,
        };

        // Group-by mode: collapsible header counts for the filtered set.
        if (in_array($groupBy, $allowedGroup, true)) {
            $groups = (clone $q)
                ->selectRaw("coalesce({$groupBy}, '—') as grp, count(*) as c")
                ->groupBy('grp')->orderByDesc('c')->get()
                ->map(fn ($r) => ['key' => $r->grp, 'count' => (int) $r->c])->values();

            return ['mode' => 'groups', 'group_by' => $groupBy, 'total' => (int) $groups->sum('count'), 'groups' => $groups];
        }

        // Flat paginated list.
        $p = $q->orderBy('title')->paginate($perPage);

        return [
            'mode' => 'list',
            'total' => $p->total(),
            'page' => $p->currentPage(),
            'per_page' => $p->perPage(),
            'last_page' => $p->lastPage(),
            'sources' => collect($p->items())->map(fn ($r) => [
                'id' => $r->id, 'kind' => $r->kind, 'title' => $r->title, 'path' => $r->path,
                'section' => $r->section, 'doc_type' => $r->doc_type,
                'mdm_vendor' => $r->mdm_vendor, 'data_platform' => $r->data_platform,
                'domain' => $r->domain, 'scope' => $r->scope,
                'product' => $r->product, 'product_version' => $r->product_version,
                'approved' => (bool) $r->approved, 'needs_metadata' => (bool) $r->needs_metadata,
                'ingest_status' => $r->ingest_status, 'updated' => $r->updated,
            ])->values(),
        ];
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
