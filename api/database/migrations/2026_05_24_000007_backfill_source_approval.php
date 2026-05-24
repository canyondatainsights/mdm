<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Turn on approval gating safely. Existing sources are backfilled to approved=true so current
 * answers keep working, and the column default flips to true so seeded/ingested content stays
 * visible — only steward uploads land pending (set explicitly in Source::markQueued).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE sources ALTER COLUMN approved SET DEFAULT true');
        DB::table('sources')->update(['approved' => true]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sources ALTER COLUMN approved SET DEFAULT false');
    }
};
