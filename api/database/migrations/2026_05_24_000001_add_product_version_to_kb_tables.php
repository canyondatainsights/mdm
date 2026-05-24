<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds product + version as first-class metadata so the KB can be expanded and
 * retrieved by product (e.g. "Customer 360") and version (e.g. "10.5", "SaaS 2024.x")
 * within a vendor. Nullable everywhere so existing rows and neutral content are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['chunks', 'sources', 'wiki_pages'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('product', 128)->nullable();
                $t->string('product_version', 64)->nullable();
            });
        }

        // Filtered-retrieval index: vendor + product + version.
        Schema::table('chunks', function (Blueprint $t) {
            $t->index(['mdm_vendor', 'product', 'product_version'], 'chunks_product_idx');
        });
    }

    public function down(): void
    {
        Schema::table('chunks', function (Blueprint $t) {
            $t->dropIndex('chunks_product_idx');
        });

        foreach (['chunks', 'sources', 'wiki_pages'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['product', 'product_version']);
            });
        }
    }
};
