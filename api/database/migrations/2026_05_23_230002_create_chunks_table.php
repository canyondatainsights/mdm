<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $dim = (int) config('mdm.embeddings.dim', 1024);

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        DB::statement(<<<SQL
            CREATE TABLE chunks (
                id              bigserial PRIMARY KEY,
                source_kind     varchar(16) NOT NULL,            -- wiki | raw
                source_path     varchar(1024) NOT NULL,          -- relative to kb/
                wiki_page_id    bigint NULL REFERENCES wiki_pages(id) ON DELETE CASCADE,
                source_id       bigint NULL REFERENCES sources(id) ON DELETE CASCADE,
                anchor          varchar(512) NULL,               -- nearest heading
                chunk_index     integer NOT NULL DEFAULT 0,
                content         text NOT NULL,
                token_count     integer NOT NULL DEFAULT 0,
                content_hash    varchar(64) NOT NULL,
                -- isolation metadata
                mdm_vendor      varchar(64) NULL,
                data_platform   varchar(64) NULL,
                financial_model varchar(64) NULL,
                domain          varchar(64) NOT NULL DEFAULT 'general',
                scope           varchar(32) NOT NULL DEFAULT 'vendor-specific',
                embedding       vector($dim) NULL,
                created_at      timestamptz NULL,
                updated_at      timestamptz NULL
            )
        SQL);

        // Metadata filter index (the vendor-isolation WHERE clause).
        DB::statement('CREATE INDEX chunks_isolation_idx ON chunks (mdm_vendor, data_platform, financial_model, domain, scope)');
        DB::statement('CREATE INDEX chunks_source_path_idx ON chunks (source_path)');
        // Approximate-NN index for cosine distance.
        DB::statement('CREATE INDEX chunks_embedding_idx ON chunks USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS chunks');
    }
};
