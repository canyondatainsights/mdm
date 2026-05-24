<?php

namespace App\Services\Kb;

use App\Models\Chunk;
use App\Models\Source;
use App\Models\StewardshipTask;
use App\Models\TaxonomyTerm;
use App\Models\WikiPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Knowledge-base coverage aggregates, shared by the API stats endpoint (MetaController) and the
 * Filament dashboard widgets so the "how deep is the KB?" numbers come from one place.
 */
class KbStats
{
    /** @return array{wiki_pages:int, sources:int, chunks:int, approved:int, pending_sources:int} */
    public function totals(): array
    {
        return [
            'wiki_pages' => WikiPage::count(),
            'sources' => Source::count(),
            'chunks' => Chunk::count(),
            'approved' => Source::where('approved', true)->count(),
            'pending_sources' => Source::where('approved', false)->count(),
        ];
    }

    /**
     * Chunk count per MDM vendor (NULL folded to "(neutral)") — vendor depth.
     *
     * @return array<string,int>
     */
    public function byVendor(): array
    {
        return $this->countBy('mdm_vendor');
    }

    /**
     * Chunk count per subject/domain — subject-matter depth.
     *
     * @return array<string,int>
     */
    public function byDomain(): array
    {
        return $this->countBy('domain');
    }

    /**
     * Chunk count per data platform.
     *
     * @return array<string,int>
     */
    public function byPlatform(): array
    {
        return $this->countBy('data_platform');
    }

    /** Pending stewardship requests (open steward work). */
    public function pendingStewardship(): int
    {
        return StewardshipTask::where('status', 'pending')->count();
    }

    /** @return array{vendors:int, domains:int, platforms:int, products:int} active taxonomy term counts */
    public function taxonomyDepth(): array
    {
        if (! Schema::hasTable('taxonomy_terms')) {
            return ['vendors' => 0, 'domains' => 0, 'platforms' => 0, 'products' => 0];
        }
        $by = fn (string $type) => TaxonomyTerm::where('active', true)->where('type', $type)->count();

        return [
            'vendors' => $by('mdm_vendor'),
            'domains' => $by('domain'),
            'platforms' => $by('data_platform'),
            'products' => $by('product'),
        ];
    }

    /**
     * Group chunks by a column, folding NULL into "(neutral)", ordered by count desc.
     *
     * @return array<string,int>
     */
    private function countBy(string $column): array
    {
        return DB::table('chunks')
            ->selectRaw("coalesce($column, '(neutral)') as k, count(*) as c")
            ->groupBy('k')
            ->orderByDesc('c')
            ->pluck('c', 'k')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
