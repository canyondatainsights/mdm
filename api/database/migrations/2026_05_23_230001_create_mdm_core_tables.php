<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('New conversation');
            // Locked stack — immutable for the life of the conversation.
            $table->string('mdm_vendor')->nullable();
            $table->string('data_platform')->nullable();
            $table->string('financial_model')->nullable();
            $table->jsonb('domains')->default('[]');
            $table->boolean('pii_redacted')->default(true);
            $table->boolean('pinned')->default(false);
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // user | assistant
            $table->jsonb('content');             // user: {text}; assistant: Block[]
            $table->jsonb('citations')->nullable();
            $table->string('confidence')->nullable();
            $table->string('model')->nullable();
            $table->jsonb('usage')->nullable();
            $table->timestamps();
        });

        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('doc_type')->default('PDF'); // PDF|DOCX|XLSX|PPTX|Confluence|MD|TXT
            $table->string('path');                       // relative to kb/
            $table->string('owner')->nullable();
            $table->integer('pages')->nullable();
            $table->jsonb('tags')->default('[]');
            $table->string('mdm_vendor')->nullable();
            $table->string('data_platform')->nullable();
            $table->string('financial_model')->nullable();
            $table->string('domain')->default('general');
            $table->string('scope')->default('vendor-specific'); // vendor-specific | neutral
            $table->boolean('approved')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('wiki_pages', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();   // relative to kb/, e.g. wiki/02-informatica-mdm/customer-360.md
            $table->string('title');
            $table->string('section')->nullable();
            $table->string('mdm_vendor')->nullable();
            $table->string('data_platform')->nullable();
            $table->string('financial_model')->nullable();
            $table->string('domain')->default('general');
            $table->string('scope')->default('vendor-specific');
            $table->timestamp('page_updated_at')->nullable(); // from front-matter / revision log
            $table->string('content_hash')->nullable();
            $table->timestamps();
        });

        Schema::create('stewardship_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('wiki_edit'); // wiki_edit | adr | source
            $table->string('target_path')->nullable();
            $table->string('summary');
            $table->longText('proposed_content')->nullable();
            $table->longText('diff')->nullable();
            $table->string('status')->default('pending');  // pending | approved | rejected
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->jsonb('meta')->nullable();
            $table->string('git_commit')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable(); // encrypted at the model layer
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('stewardship_tasks');
        Schema::dropIfExists('wiki_pages');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
