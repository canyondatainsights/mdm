<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags a source whose required metadata (vendor + product) couldn't be determined
 * (from the form, front-matter, or auto-parsing). Flagged sources are ingested but
 * held out of retrieval until a steward supplies the tags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $t) {
            $t->boolean('needs_metadata')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $t) {
            $t->dropColumn('needs_metadata');
        });
    }
};
