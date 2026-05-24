<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reference-data table for the lockable taxonomy (vendors, platforms, financial models,
 * subjects/domains, and per-vendor products). Replaces the hardcoded config('mdm.dimensions'
 * / 'products') so terms can be added on demand from the admin without a code change.
 * Seeded from the current config so behaviour is unchanged until an admin edits it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_terms', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();      // mdm_vendor | data_platform | financial_model | domain | product
            $table->string('value');              // canonical value (stored on chunks/sources)
            $table->string('label')->nullable();  // optional display label (defaults to value)
            $table->string('vendor')->nullable(); // owning vendor slug, for type=product
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['type', 'value', 'vendor']);
        });

        // Seed from existing config defaults so nothing changes until an admin edits the table.
        $now = now();
        $rows = [];
        foreach ((array) config('mdm.dimensions', []) as $type => $values) {
            $i = 0;
            foreach ((array) $values as $v) {
                $rows[] = ['type' => $type, 'value' => $v, 'label' => null, 'vendor' => null, 'sort_order' => $i++, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        foreach ((array) config('mdm.products', []) as $vendor => $values) {
            $i = 0;
            foreach ((array) $values as $v) {
                $rows[] = ['type' => 'product', 'value' => $v, 'label' => null, 'vendor' => $vendor, 'sort_order' => $i++, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('taxonomy_terms')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_terms');
    }
};
