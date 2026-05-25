<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved research topics for deep-dives. Scope `private` (owner only) or `shared` (visible to all
 * users — "group research"). An optional locked stack lets a topic launch a pre-locked conversation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('scope')->default('private'); // private | shared
            $table->string('mdm_vendor')->nullable();
            $table->string('data_platform')->nullable();
            $table->string('financial_model')->nullable();
            $table->json('domains')->nullable();
            $table->json('extensions')->nullable();
            $table->timestamps();
            $table->index(['scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_topics');
    }
};
