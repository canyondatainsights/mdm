<?php

namespace App\Services\Retrieval;

use App\Models\Conversation;
use App\Services\Embeddings\Embedder;
use Illuminate\Support\Facades\DB;

/**
 * Vendor-isolated retrieval over pgvector.
 *
 * The isolation rule, per dimension: a chunk is eligible only if it is UNTAGGED on that
 * dimension (NULL) or matches the conversation's lock. So a Snowflake-tagged chunk can
 * never surface in a Databricks-locked conversation, and an SAP chunk never surfaces in
 * an Informatica-locked one. Vendor-neutral content (NULL on every dimension) is shared.
 */
class Retriever
{
    public function __construct(private Embedder $embedder) {}

    /**
     * @return array<int, array{id:int, source_kind:string, source_path:string, anchor:?string,
     *               content:string, mdm_vendor:?string, data_platform:?string, domain:string,
     *               scope:string, distance:float}>
     */
    public function retrieve(Conversation $conversation, string $query, ?int $k = null): array
    {
        $k = $k ?? (int) config('mdm.retrieval.top_k', 8);
        $stack = $conversation->lockedStack();

        [$vector] = $this->embedder->embed([$query]);
        $literal = '['.implode(',', $vector).']';

        $rows = $this->baseQuery($stack)
            ->selectRaw('chunks.embedding <=> ?::vector AS distance', [$literal])
            ->whereNotNull('chunks.embedding')
            ->orderByRaw('chunks.embedding <=> ?::vector', [$literal])
            ->limit($k)
            ->get();

        return $rows->map(fn ($r) => (array) $r)->all();
    }

    /** Count eligible chunks for a locked stack (used by tests / diagnostics). */
    public function eligibleCount(Conversation $conversation): int
    {
        return $this->baseQuery($conversation->lockedStack())->count();
    }

    private function baseQuery(array $stack)
    {
        // Join the parent source/wiki row so citations can reference the ORIGINAL source
        // (URL for fetched docs, file, or wiki page) with its metadata (title, date).
        $q = DB::table('chunks')
            ->leftJoin('sources', 'sources.id', '=', 'chunks.source_id')
            ->leftJoin('wiki_pages', 'wiki_pages.id', '=', 'chunks.wiki_page_id')
            ->select([
                'chunks.id', 'chunks.source_kind', 'chunks.source_path', 'chunks.anchor', 'chunks.content',
                'chunks.mdm_vendor', 'chunks.data_platform', 'chunks.financial_model', 'chunks.domain', 'chunks.scope',
                'chunks.product', 'chunks.product_version',
                'sources.title as source_title', 'sources.owner as source_origin',
                'sources.doc_type as source_doc_type', 'sources.created_at as source_created',
                'wiki_pages.title as wiki_title', 'wiki_pages.page_updated_at as page_date',
            ]);

        // Hold incomplete sources out of retrieval until a steward tags them (vendor + product),
        // and exclude superseded copies (older duplicates / earlier versions).
        $q->where(fn ($w) => $w->whereNull('sources.id')->orWhere('sources.needs_metadata', false));
        $q->where(fn ($w) => $w->whereNull('sources.id')->orWhere('sources.superseded', false));

        // Hard vendor / platform / financial-model isolation (qualified — joined tables share these names).
        $q->where(fn ($w) => $w->whereNull('chunks.mdm_vendor')->orWhere('chunks.mdm_vendor', $stack['mdm_vendor']));
        $q->where(fn ($w) => $w->whereNull('chunks.data_platform')->orWhere('chunks.data_platform', $stack['data_platform']));
        $q->where(fn ($w) => $w->whereNull('chunks.financial_model')->orWhere('chunks.financial_model', $stack['financial_model']));

        // Domain scoping. When the stack pins domains, restrict to those — but still include the
        // locked vendor's OWN 'general' docs (e.g. Informatica capability docs like CDGC/DQ that
        // aren't a data-domain). Only cross-vendor/neutral 'general' content (mdm_vendor NULL) is
        // excluded as filler. When no domains are pinned, don't constrain by domain.
        $domains = $stack['domains'] ?: [];
        if (! empty($domains)) {
            $vendor = $stack['mdm_vendor'] ?? null;
            $q->where(function ($w) use ($domains, $vendor) {
                $w->whereIn('chunks.domain', $domains);
                if ($vendor) {
                    $w->orWhere(fn ($w2) => $w2->where('chunks.domain', 'general')->where('chunks.mdm_vendor', $vendor));
                }
            });
        }

        // Optional product / version scoping (when the conversation pins them).
        if (! empty($stack['product'])) {
            $q->where(fn ($w) => $w->whereNull('chunks.product')->orWhere('chunks.product', $stack['product']));
        }
        if (! empty($stack['product_version'])) {
            $q->where(fn ($w) => $w->whereNull('chunks.product_version')->orWhere('chunks.product_version', $stack['product_version']));
        }

        return $q;
    }
}
