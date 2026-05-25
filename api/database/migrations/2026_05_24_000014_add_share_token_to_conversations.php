<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in public share links: an unguessable token that exposes a read-only transcript at
 * /share/<token>. Null = not shared; revoking nulls it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('pinned');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('share_token');
        });
    }
};
