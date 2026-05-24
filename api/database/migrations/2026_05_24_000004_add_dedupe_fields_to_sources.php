<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedupe support: content_hash to detect identical re-uploads, and a superseded flag so
 * an older copy (same content, or same doc at an older date/version) is kept out of
 * retrieval in favour of the latest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $t) {
            $t->string('content_hash', 64)->nullable()->index();
            $t->boolean('superseded')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $t) {
            $t->dropColumn(['content_hash', 'superseded']);
        });
    }
};
