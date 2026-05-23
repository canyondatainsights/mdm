<?php

return [
    // Absolute path to the knowledge base root (contains wiki/ raw/ output/).
    // A relative KB_PATH is resolved against the Laravel base path.
    'kb_path' => (function () {
        $p = env('KB_PATH', '../kb');
        return str_starts_with($p, '/') ? $p : base_path($p);
    })(),

    'anthropic' => [
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 2048),
        // API key is resolved at runtime: DB setting (admin UI) first, then this env value.
        'env_key' => env('ANTHROPIC_API_KEY'),
    ],

    'embeddings' => [
        // voyage | sidecar | fake
        'driver' => env('EMBEDDINGS_DRIVER', 'fake'),
        'dim' => (int) env('EMBEDDINGS_DIM', 1024),
        'voyage' => [
            'key' => env('VOYAGE_API_KEY'),
            'model' => env('VOYAGE_MODEL', 'voyage-3'),
            'url' => env('VOYAGE_URL', 'https://api.voyageai.com/v1/embeddings'),
        ],
        'sidecar' => [
            'url' => env('EMBEDDINGS_SIDECAR_URL', 'http://127.0.0.1:8001'),
        ],
    ],

    'retrieval' => [
        'top_k' => (int) env('RETRIEVAL_TOP_K', 8),
    ],

    'chunking' => [
        'max_tokens' => 950,   // approximate; ~4 chars/token heuristic
        'overlap_tokens' => 130,
    ],

    // Allowed values for the lockable stack dimensions. Extend to add vendors/platforms.
    'dimensions' => [
        'mdm_vendor' => ['informatica', 'sap', 'profisee', 'reltio', 'ataccama', 'stibo'],
        'data_platform' => ['databricks', 'snowflake', 'bigquery', 'synapse'],
        'financial_model' => ['isda-cdm', 'fpml', 'fibo'],
        'domain' => ['customer', 'product', 'vendor', 'supplier', 'finance', 'healthcare', 'general'],
    ],

    // Phrases that signal the user wants to enrich the KB (creates a stewardship task).
    'enrichment_triggers' => [
        'capture this to the wiki',
        'add this to the kb',
        'add this to the knowledge base',
        'refresh this topic',
        'ingest this file',
    ],
];
