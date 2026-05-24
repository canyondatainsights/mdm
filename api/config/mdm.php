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

    'chat' => [
        // How many prior messages to replay as context (conversation memory).
        'history_turns' => (int) env('CHAT_HISTORY_TURNS', 10),
    ],

    'chunking' => [
        'max_tokens' => 950,   // approximate; ~4 chars/token heuristic
        'overlap_tokens' => 130,
    ],

    // OCR fallback for scanned/image PDFs (embedded-text extraction yields little).
    // Requires poppler (pdftoppm) + tesseract on PATH (brew install poppler tesseract).
    'ocr' => [
        'enabled' => (bool) env('OCR_ENABLED', true),
        'tesseract' => env('TESSERACT_BIN', 'tesseract'),
        'pdftoppm' => env('PDFTOPPM_BIN', 'pdftoppm'),
        // poppler text/info tools — robust, low-memory PDF text extraction (vs in-PHP parsing).
        'pdftotext' => env('PDFTOTEXT_BIN', 'pdftotext'),
        'pdfinfo' => env('PDFINFO_BIN', 'pdfinfo'),
        'dpi' => (int) env('OCR_DPI', 200),
        'max_pages' => (int) env('OCR_MAX_PAGES', 80), // cap OCR work per doc
    ],

    // Allowed values for the lockable stack dimensions. Extend to add vendors/platforms.
    'dimensions' => [
        'mdm_vendor' => ['informatica', 'sap', 'profisee', 'reltio', 'ataccama', 'stibo'],
        'data_platform' => ['databricks', 'snowflake', 'bigquery', 'synapse'],
        'financial_model' => ['isda-cdm', 'fpml', 'fibo'],
        'domain' => ['customer', 'product', 'vendor', 'supplier', 'finance', 'healthcare', 'general'],
    ],

    // Known products per vendor — drives the upload form's product picker (free-text
    // fallback allowed). Versions are free-text (e.g. '10.5', 'SaaS 2024.x'), not enumerated.
    'products' => [
        'informatica' => [
            'MDM Hub', 'Customer 360', 'Supplier 360', 'Product 360', 'Reference 360',
            'IDQ', 'IDMC', 'Data Director', 'Provisioning Tool',
        ],
        'sap' => ['Master Data Governance', 'S/4HANA MDG'],
        'profisee' => ['Profisee Platform'],
        'reltio' => ['Reltio Connected Data Platform'],
        'ataccama' => ['Ataccama ONE'],
        'stibo' => ['STEP'],
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
