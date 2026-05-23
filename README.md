# MDM Knowledge Hub

A multi-user, chat-queryable knowledge platform for Master Data Management. It pairs a
governed Markdown knowledge base (Informatica MDM, data quality, Databricks/Snowflake
pipelines, governance & consent, financial data models) with a senior-data-architect
LLM assistant that answers **with citations** and **never mixes vendors**.

> The knowledge base content lives in [`kb/`](kb/) and is portable plain Markdown. The
> application layer (this repo's `api/` + `web/`) is additive — the `kb/` tree remains
> readable and transferable on its own (see [`kb/MANIFEST.md`](kb/MANIFEST.md)).

## Architecture (hybrid)

| Part | Stack | Responsibility |
|---|---|---|
| `api/` | **Laravel** (PHP 8.4) + **Filament** + **PostgreSQL/pgvector** | REST + SSE API, auth, retrieval with vendor isolation, ingestion (queues/scheduler), admin/governance panel, Claude via Prism |
| `web/` | **Next.js** (TypeScript) + Tailwind + `lucide-react` | The three-pane chat UI (Conversations · Chat · Source Inspector) ported from the design handoff |
| `kb/`  | Plain Markdown (git-backed) | The knowledge: `wiki/` (curated), `raw/` (sources), `output/` (exports + Q&A log) |
| `embeddings/` | Python FastAPI (optional) | Local embedding sidecar (alternative to the Voyage AI API) |

### Vendor isolation (hard requirement)

Every chunk is tagged with `mdm_vendor`, `data_platform`, `financial_model`, `domain`, and
`scope`. A conversation **locks a stack up front** (e.g. Informatica + Databricks +
Customer); retrieval hard-filters on those dimensions in SQL, so answers can never mix
Informatica with SAP/Profisee/Reltio/Ataccama, or Databricks with Snowflake. Vendor-neutral
content (foundations, governance, GDPR) is shared across all stacks.

## Quick start (local, without Docker)

```bash
# 1. Postgres (with pgvector) — create db `mdm` and enable the extension
#    psql -d mdm -c 'CREATE EXTENSION IF NOT EXISTS vector;'

# 2. API
cd api
cp .env.example .env        # set DB_*, ANTHROPIC_API_KEY (or set the key in the admin UI)
composer install
php artisan key:generate
php artisan migrate
php artisan kb:ingest        # index kb/wiki + kb/raw into pgvector
php artisan serve            # http://127.0.0.1:8000

# 3. Web
cd ../web
cp .env.example .env.local   # NEXT_PUBLIC_API_URL=http://127.0.0.1:8000
npm install
npm run dev                  # http://localhost:3000
```

## Quick start (Docker, for hosting)

```bash
docker compose up --build    # postgres+pgvector, api, queue worker, scheduler, web, (embeddings), proxy
```

See [`docker-compose.yml`](docker-compose.yml). Target a long-running host with a persistent
volume (VM / container service) — the app writes files (KB enrichment, uploads) and keeps
an on-disk vector index, so it is **not** suited to read-only serverless platforms.

## Documentation

- Knowledge base operating contract: [`kb/MANIFEST.md`](kb/MANIFEST.md)
- Topic index: [`kb/_INDEX.md`](kb/_INDEX.md)
- Change history: [`kb/_CHANGELOG.md`](kb/_CHANGELOG.md)
- Implementation plan: see the approved plan referenced in the project history.
