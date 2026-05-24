<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks where an uploaded source is in the ingestion pipeline so the UI can show
 * progress and tell the user when the content becomes available for answers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $t) {
            // queued | processing | ready | failed
            $t->string('ingest_status', 16)->default('ready')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $t) {
            $t->dropColumn('ingest_status');
        });
    }
};
