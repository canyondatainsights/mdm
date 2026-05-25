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
        'domain' => [
            'customer', 'product', 'vendor', 'supplier', 'finance', 'healthcare',
            // IDMC capability "subjects" (not data-domains, but lockable topics).
            'data-governance', 'data-quality', 'data-profiling', 'data-privacy', 'parsing', 'address-verification',
            'general',
        ],
    ],

    // Known products per vendor — drives the upload form's product picker (free-text
    // fallback allowed). Versions are free-text (e.g. '10.5', 'SaaS 2024.x'), not enumerated.
    'products' => [
        'informatica' => [
            'MDM Hub', 'Multidomain MDM', 'Customer 360', 'Supplier 360', 'Product 360', 'Reference 360',
            'CDGC', 'Cloud Data Governance and Catalog', 'Cloud Data Quality', 'Cloud Data Profiling',
            'Address Verification', 'Data as a Service', 'IDQ', 'IDMC', 'Data Director', 'Provisioning Tool',
        ],
        'sap' => ['Master Data Governance', 'S/4HANA MDG'],
        'profisee' => ['Profisee Platform'],
        'reltio' => ['Reltio Connected Data Platform'],
        'ataccama' => ['Ataccama ONE'],
        'stibo' => ['STEP'],
    ],

    // Allowed upload file types. 'pdf' is parsed via poppler/OCR; everything else here is read
    // as UTF-8 text (docs + example scripts/code, so .sql/.py/etc. ingest, retrieve, and cite
    // like any document). Extend to accept more script/code types.
    'uploads' => [
        // Max files accepted in a single upload request (guards against accidental bulk dumps).
        'max_files' => (int) env('UPLOAD_MAX_FILES', 20),
        'extensions' => [
            'pdf', 'md', 'markdown', 'txt',
            'sql', 'py', 'json', 'yaml', 'yml', 'xml', 'js', 'ts', 'tsx', 'jsx',
            'sh', 'bash', 'scala', 'java', 'rb', 'go', 'csv', 'tsv', 'ini', 'conf', 'properties', 'toml', 'r',
        ],
    ],

    // Documentation crawler profiles (php artisan kb:crawl <vendor>). Each profile names the
    // sitemap(s), path patterns to exclude, and a `sections` map (first matching URL path segment
    // => [product|null, domain]) that BOTH selects which pages to crawl (targeted scope) and
    // classifies them. Crawled pages are tagged data_platform=<platform>, mdm_vendor=null so they
    // surface in any conversation on that platform.
    'crawlers' => [
        'databricks' => [
            'platform' => 'databricks',
            'sitemaps' => ['https://docs.databricks.com/aws/en/sitemap.xml'], // aws/en (gcp ~dup; skip ja/pt)
            'exclude' => ['/release-notes/', '/error-messages/', '/archive/', '?s=', '/_static/'],
            // Order matters (first matching path segment wins): data-quality segments are listed
            // before governance so a /data-governance/unity-catalog/data-quality-monitoring/ URL is
            // tagged data-quality, not data-governance.
            'sections' => [
                'data-quality-monitoring' => [null, 'data-quality'],
                'lakehouse-monitoring' => [null, 'data-quality'],
                'ldp' => ['Databricks Delta Live Tables', 'data-quality'], // Lakeflow Declarative Pipelines (expectations/constraints)
                'unity-catalog' => ['Databricks Unity Catalog', 'data-governance'],
                'catalogs' => ['Databricks Unity Catalog', 'data-governance'],
                'data-governance' => ['Databricks Unity Catalog', 'data-governance'],
                'security' => [null, 'data-governance'],
                'delta' => ['Delta Lake', 'general'],
                'delta-sharing' => [null, 'general'],
                'sql' => ['Databricks SQL', 'general'],
                'structured-streaming' => [null, 'general'],
                'ingestion' => [null, 'general'],
                'machine-learning' => ['Databricks Machine Learning', 'general'],
                'lakehouse' => ['Databricks Lakehouse Platform', 'general'],
            ],
        ],
        'snowflake' => [
            'platform' => 'snowflake',
            'sitemaps' => ['https://docs.snowflake.com/en/sitemap.xml'],
            'exclude' => ['/sql-reference', '/INCLUDE/', '/DRAFT/', '/PREVIEW/', '/release-notes/', '/api-reference', '/migrations/'],
            // Snowflake's topics live in leaf names under user-guide/developer-guide (not directory
            // segments), so these use substring `match` patterns (evaluated in order, first wins) to
            // mirror the Databricks categories: governance, data-quality, sharing, ingestion,
            // streaming, ML, tables, SQL/warehouse, developer.
            'sections' => [
                'data-governance' => ['product' => 'Snowflake Horizon', 'domain' => 'data-governance', 'match' => [
                    'governance', 'access-control', 'masking', 'row-access', 'column-level', 'object-tag', 'tag-based',
                    'classification', 'privacy', 'trust-center', 'authentication', 'oauth', 'scim', 'network-polic',
                    'encryption', 'key-pair', 'rbac', 'security',
                ]],
                'data-quality' => ['product' => null, 'domain' => 'data-quality', 'match' => [
                    'data-quality', 'data-metric', 'dmf',
                ]],
                'data-sharing' => ['product' => 'Snowflake Data Sharing', 'domain' => 'general', 'match' => [
                    'data-sharing', 'sharing', 'listing', 'data-exchange', 'collaboration', 'clean-room', 'cleanroom',
                ]],
                'ingestion' => ['product' => null, 'domain' => 'general', 'match' => [
                    'snowpipe', 'data-load', 'kafka', 'ingest', 'connector',
                ]],
                'streaming' => ['product' => 'Snowflake Dynamic Tables', 'domain' => 'general', 'match' => [
                    'streams', 'streaming', 'dynamic-table',
                ]],
                'machine-learning' => ['product' => 'Snowflake Cortex', 'domain' => 'general', 'match' => [
                    'cortex', 'ml-function', 'snowflake-ml',
                ]],
                'tables' => ['product' => null, 'domain' => 'general', 'match' => [
                    'iceberg', 'external-table', 'hybrid-table', 'tables',
                ]],
                'sql-warehouse' => ['product' => 'Snowflake Data Warehouse', 'domain' => 'general', 'match' => [
                    'warehouse', 'querying', 'performance', 'search-optimization', 'clustering', 'views',
                ]],
                'developer' => ['product' => 'Snowpark', 'domain' => 'general', 'match' => [
                    'developer-guide', 'snowpark', 'snowflake-cli', 'stored-procedure', 'udf',
                ]],
            ],
        ],
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
