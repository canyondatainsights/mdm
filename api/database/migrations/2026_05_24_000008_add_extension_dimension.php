<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add an "extension" dimension so industry verticals / add-ons (consent, insurance, retail, …) are
 * first-class and opt-in: chunks/sources carry an extension (NULL = core), conversations carry the
 * set of included extensions (NULL = not chosen yet, [] = core-only). Seeds the extension taxonomy.
 */
return new class extends Migration
{
    private array $extensions = ['consent', 'insurance', 'retail', 'esg', 'sap', 'fabric', 'healthcare'];

    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->string('extension')->nullable()->index();
        });
        Schema::table('chunks', function (Blueprint $table) {
            $table->string('extension')->nullable()->index();
        });
        Schema::table('conversations', function (Blueprint $table) {
            $table->json('extensions')->nullable();
        });

        $now = now();
        $sort = 0;
        foreach ($this->extensions as $value) {
            $exists = DB::table('taxonomy_terms')->where('type', 'extension')->where('value', $value)->whereNull('vendor')->exists();
            if (! $exists) {
                DB::table('taxonomy_terms')->insert([
                    'type' => 'extension', 'value' => $value, 'label' => ucfirst($value), 'vendor' => null,
                    'sort_order' => $sort++, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sources', fn (Blueprint $t) => $t->dropColumn('extension'));
        Schema::table('chunks', fn (Blueprint $t) => $t->dropColumn('extension'));
        Schema::table('conversations', fn (Blueprint $t) => $t->dropColumn('extensions'));
        DB::table('taxonomy_terms')->where('type', 'extension')->delete();
    }
};
