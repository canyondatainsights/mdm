<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Kb\KbStats;

class MetaController extends Controller
{
    /** Lockable stack dimensions (+ per-vendor products) for the Stack-Lock / upload UI. */
    public function dimensions()
    {
        return \App\Services\Taxonomy\Taxonomy::dimensions() + ['products' => \App\Services\Taxonomy\Taxonomy::products()];
    }

    /** KB coverage stats for the sidebar / data-model explorer. */
    public function stats(KbStats $stats)
    {
        $t = $stats->totals();
        // Reshape the {key => count} maps to the {vendor|platform, chunks}[] shape the web BrowseModal expects.
        $rows = fn (array $map, string $label) => collect($map)
            ->map(fn (int $c, string $name) => [$label => $name, 'chunks' => $c])->values();

        return [
            'wiki_pages' => $t['wiki_pages'],
            'sources' => $t['sources'],
            'chunks' => $t['chunks'],
            'by_vendor' => $rows($stats->byVendor(), 'vendor'),
            'by_platform' => $rows($stats->byPlatform(), 'platform'),
            'by_domain' => $rows($stats->byDomain(), 'domain'),
        ];
    }
}
