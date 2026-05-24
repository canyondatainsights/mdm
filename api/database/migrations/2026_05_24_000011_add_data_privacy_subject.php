<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add "data-privacy" as a Data domain / subject in the lockable taxonomy (alongside data-governance,
 * data-quality, data-profiling). Idempotent — admins can add further subjects via Admin → Taxonomy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('taxonomy_terms')
            ->where(['type' => 'domain', 'value' => 'data-privacy'])->exists();

        if (! $exists) {
            DB::table('taxonomy_terms')->insert([
                'type' => 'domain',
                'value' => 'data-privacy',
                'label' => 'Data Privacy',
                'vendor' => null,
                'sort_order' => 99,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('taxonomy_terms')->where(['type' => 'domain', 'value' => 'data-privacy', 'vendor' => null])->delete();
    }
};
