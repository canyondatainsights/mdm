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
            ->selectRaw('embedding <=> ?::vector AS distance', [$literal])
            ->whereNotNull('embedding')
            ->orderByRaw('embedding <=> ?::vector', [$literal])
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
        $q = DB::table('chunks')->select([
            'id', 'source_kind', 'source_path', 'anchor', 'content',
            'mdm_vendor', 'data_platform', 'financial_model', 'domain', 'scope',
        ]);

        // Hard vendor / platform / financial-model isolation.
        $q->where(fn ($w) => $w->whereNull('mdm_vendor')->orWhere('mdm_vendor', $stack['mdm_vendor']));
        $q->where(fn ($w) => $w->whereNull('data_platform')->orWhere('data_platform', $stack['data_platform']));
        $q->where(fn ($w) => $w->whereNull('financial_model')->orWhere('financial_model', $stack['financial_model']));

        // Soft domain scoping: locked domains + always-relevant 'general'.
        $domains = array_values(array_unique(array_merge($stack['domains'] ?: [], ['general'])));
        $q->whereIn('domain', $domains);

        return $q;
    }
}
