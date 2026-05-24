<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * DB-backed crawler profiles so stewards can manage doc crawlers (and add new sites) from the admin
 * and run ad-hoc crawls — replacing the static config('mdm.crawlers'). Seeded from that config, with
 * each section normalized to {section, product, domain, match[]} (match empty = exact path-segment
 * match; otherwise substring match).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawlers', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // profile key (databricks, snowflake, …)
            $table->string('name');
            $table->string('platform');               // data_platform tag applied to crawled pages
            $table->json('sitemaps');                 // ["https://…/sitemap.xml", …]
            $table->json('exclude');                  // ["/release-notes/", …]
            $table->json('sections');                 // [{section, product, domain, match:[]}]
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $now = now();
        $i = 0;
        foreach ((array) config('mdm.crawlers', []) as $key => $profile) {
            $sections = [];
            foreach ((array) ($profile['sections'] ?? []) as $label => $def) {
                if (array_is_list($def)) {
                    $sections[] = ['section' => (string) $label, 'product' => $def[0] ?? null, 'domain' => $def[1] ?? 'general', 'match' => []];
                } else {
                    $sections[] = ['section' => (string) $label, 'product' => $def['product'] ?? null, 'domain' => $def['domain'] ?? 'general', 'match' => array_values($def['match'] ?? [])];
                }
            }
            DB::table('crawlers')->insert([
                'key' => $key,
                'name' => Str::title($key),
                'platform' => $profile['platform'] ?? $key,
                'sitemaps' => json_encode(array_values($profile['sitemaps'] ?? [])),
                'exclude' => json_encode(array_values($profile['exclude'] ?? [])),
                'sections' => json_encode($sections),
                'active' => true,
                'sort_order' => $i++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crawlers');
    }
};
