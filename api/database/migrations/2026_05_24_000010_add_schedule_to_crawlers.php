<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-crawler scheduling: a frequency (off|daily|weekly|monthly) and the last automated run time, so
 * `crawlers:run-scheduled` (registered in routes/console.php) can refresh doc sites without manual runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawlers', function (Blueprint $table) {
            $table->string('schedule')->default('off')->after('active'); // off|daily|weekly|monthly
            $table->timestamp('last_run_at')->nullable()->after('schedule');
        });
    }

    public function down(): void
    {
        Schema::table('crawlers', function (Blueprint $table) {
            $table->dropColumn(['schedule', 'last_run_at']);
        });
    }
};
