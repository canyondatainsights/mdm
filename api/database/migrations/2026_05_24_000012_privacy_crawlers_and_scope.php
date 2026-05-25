<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generalize crawlers to "topic/subject" mode: platform becomes optional and a `scope` is stored, so a
 * crawler can tag pages domain=<subject>, scope=neutral (no platform/vendor) instead of always
 * data_platform=<platform>. Seeds two data-privacy crawlers (gdpr-info.eu, cppa.ca.gov) that catch-all
 * their site (section match ['/']) and tag domain=data-privacy, scope=neutral.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawlers', function (Blueprint $table) {
            $table->string('platform')->nullable()->change();
            $table->string('scope')->default('vendor-specific')->after('platform');
        });

        $now = now();
        $seed = [
            [
                'key' => 'gdpr-info',
                'name' => 'GDPR (gdpr-info.eu)',
                'sitemaps' => ['https://gdpr-info.eu/sitemap.xml'],
                'exclude' => ['?', '/de/', '/fr/', '/it/', '/es/', '/nl/', '/pl/', '/pt-br/', '/ro/', '/cs/', '/issues/'],
                'sections' => [['section' => 'gdpr', 'product' => null, 'domain' => 'data-privacy', 'match' => ['/']]],
            ],
            [
                'key' => 'cppa-ca-gov',
                'name' => 'California CPPA (CCPA/CPRA)',
                'sitemaps' => ['https://cppa.ca.gov/sitemap.xml'],
                'exclude' => ['?', '/meetings', '/events', '/press', '/news', '/careers', '/announcements', '/board'],
                'sections' => [['section' => 'ccpa', 'product' => null, 'domain' => 'data-privacy', 'match' => ['/']]],
            ],
        ];

        $sort = (int) DB::table('crawlers')->max('sort_order') + 1;
        foreach ($seed as $c) {
            if (DB::table('crawlers')->where('key', $c['key'])->exists()) {
                continue;
            }
            DB::table('crawlers')->insert([
                'key' => $c['key'],
                'name' => $c['name'],
                'platform' => null,
                'scope' => 'neutral',
                'sitemaps' => json_encode($c['sitemaps']),
                'exclude' => json_encode($c['exclude']),
                'sections' => json_encode($c['sections']),
                'active' => true,
                'schedule' => 'off',
                'sort_order' => $sort++,
                'notes' => 'Topic crawler — tags domain=data-privacy, scope=neutral (no platform).',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('crawlers')->whereIn('key', ['gdpr-info', 'cppa-ca-gov'])->delete();
        Schema::table('crawlers', function (Blueprint $table) {
            $table->dropColumn('scope');
            // platform left nullable — harmless.
        });
    }
};
