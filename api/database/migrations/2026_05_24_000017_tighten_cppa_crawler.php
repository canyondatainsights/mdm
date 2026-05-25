<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tighten the cppa.ca.gov crawler from a catch-all to a targeted match — keep the regulation / law /
 * data-broker / enforcement content, drop low-value site chrome (FAQ, Jobs, Accessibility, About,
 * web apps, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        $sections = [[
            'section' => 'ccpa',
            'product' => null,
            'domain' => 'data-privacy',
            'match' => ['regulations', 'data_broker', 'enforcement', 'rulemaking', 'consumer_privacy'],
        ]];

        DB::table('crawlers')->where('key', 'cppa-ca-gov')->update(['sections' => json_encode($sections)]);
    }

    public function down(): void
    {
        $sections = [['section' => 'ccpa', 'product' => null, 'domain' => 'data-privacy', 'match' => ['/']]];
        DB::table('crawlers')->where('key', 'cppa-ca-gov')->update(['sections' => json_encode($sections)]);
    }
};
