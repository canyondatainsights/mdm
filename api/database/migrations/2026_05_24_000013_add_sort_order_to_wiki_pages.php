<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-page ordering for wiki pages so stewards can drag-reorder pages within a section (admin) and the
 * web wiki browser lists them in that order. Section order stays driven by the section's name prefix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wiki_pages', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('section');
        });
    }

    public function down(): void
    {
        Schema::table('wiki_pages', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
