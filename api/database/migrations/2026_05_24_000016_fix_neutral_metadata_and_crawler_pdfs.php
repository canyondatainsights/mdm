<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two fixes surfaced by the data-privacy crawl:
 *  1. A neutral subject (a non-"general" domain, e.g. data-privacy) is a valid retrieval signal, so
 *     such sources should NOT be flagged needs_metadata / held out. Backfill existing rows.
 *  2. The catch-all crawlers queued PDF links (which the HTML fetcher can't parse → junk/failed).
 *     Exclude .pdf from the gdpr-info + cppa crawlers.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sources')
            ->whereNotNull('domain')->where('domain', '!=', 'general')->where('needs_metadata', true)
            ->update(['needs_metadata' => false]);

        foreach (DB::table('crawlers')->whereIn('key', ['gdpr-info', 'cppa-ca-gov'])->get() as $c) {
            $exclude = json_decode($c->exclude ?? '[]', true) ?: [];
            foreach (['.pdf', '.docx', '.zip', '/pdf/'] as $ex) {
                if (! in_array($ex, $exclude, true)) {
                    $exclude[] = $ex;
                }
            }
            DB::table('crawlers')->where('id', $c->id)->update(['exclude' => json_encode(array_values($exclude))]);
        }
    }

    public function down(): void
    {
        // No-op: re-flagging neutral sources / restoring PDF crawling is undesirable.
    }
};
