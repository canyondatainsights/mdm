<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chunk;
use App\Models\Source;
use App\Models\WikiPage;
use Illuminate\Support\Facades\DB;

class MetaController extends Controller
{
    /** Lockable stack dimensions (+ per-vendor products) for the Stack-Lock / upload UI. */
    public function dimensions()
    {
        return config('mdm.dimensions') + ['products' => config('mdm.products')];
    }

    /** KB coverage stats for the sidebar / data-model explorer. */
    public function stats()
    {
        return [
            'wiki_pages' => WikiPage::count(),
            'sources' => Source::count(),
            'chunks' => Chunk::count(),
            'by_vendor' => DB::table('chunks')
                ->selectRaw("coalesce(mdm_vendor, '(neutral)') as vendor, count(*) as chunks")
                ->groupBy('vendor')->orderByDesc('chunks')->get(),
            'by_platform' => DB::table('chunks')
                ->selectRaw("coalesce(data_platform, '(neutral)') as platform, count(*) as chunks")
                ->groupBy('platform')->orderByDesc('chunks')->get(),
        ];
    }
}
