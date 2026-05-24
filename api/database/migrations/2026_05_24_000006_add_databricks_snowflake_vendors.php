<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add Databricks and Snowflake as MDM vendors (they already exist as data platforms — kept).
 * Their product lines are populated separately via `php artisan taxonomy:fetch-products <vendor>`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $vendors = ['databricks' => 'Databricks', 'snowflake' => 'Snowflake'];
        $sort = 50;
        foreach ($vendors as $value => $label) {
            $exists = DB::table('taxonomy_terms')
                ->where('type', 'mdm_vendor')->where('value', $value)->whereNull('vendor')->exists();
            if (! $exists) {
                DB::table('taxonomy_terms')->insert([
                    'type' => 'mdm_vendor', 'value' => $value, 'label' => $label, 'vendor' => null,
                    'sort_order' => $sort++, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('taxonomy_terms')->where('type', 'mdm_vendor')
            ->whereIn('value', ['databricks', 'snowflake'])->whereNull('vendor')->delete();
    }
};
