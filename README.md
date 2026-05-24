# Your Knowledge Hub

A multi-user, chat-queryable knowledge platform for **Master Data Management**. It pairs a
governed Markdown knowledge base (Informatica MDM/Customer 360, data quality,
Databricks/Snowflake pipelines, governance & consent, financial data models) with a
senior-data-architect LLM assistant that answers **with citations**, **never mixes
vendors**, and can **generate deliverables** (e.g. source-to-target mapping spreadsheets).

> The knowledge content lives in [`kb/`](kb/) as portable plain Markdown. The application
> layer (`api/` + `web/`) is additive — the `kb/` tree stays readable and transferable on
> its own (see [`kb/MANIFEST.md`](kb/MANIFEST.md)).

---

## Features

**Chat & retrieval**
- **Stack-locked conversations** — each conversation locks an MDM vendor + data platform
  (+ optional financial model + domains). Retrieval **hard-filters in SQL**, so answers can
  never mix Informatica with SAP/Profisee/Reltio/Ataccama, or Databricks with Snowflake.
- **Cited answers** grounded in the KB; each citation names the **original source** (URL,
  file, or wiki page) with metadata — **product, version, date** — and opens in the Inspector.
- **Semantic retrieval** over pgvector. Locked stacks get stack-specific content only (no
  generic "general" filler).
- **Conversation memory** — follow-ups like *"include the consent extension in your previous
  answer"* work (prior turns are replayed to the model).
- **Stop button** — cancel a long streaming answer; the partial response is kept.

**Knowledge expansion** (Steward/Admin)
- **Upload PDF / Markdown / TXT, or a reference URL**, from the Filament admin, a Next.js
  screen, or the chat composer's Attach. Tagged by **vendor · product · version · domain**.
- **PDF text extraction** via poppler `pdftotext` (robust, low-memory), with **OCR fallback**
  (poppler + tesseract) for scanned/image PDFs.
- **Auto-parsed metadata** — vendor/product/version/domain inferred from filename + content;
  if vendor+product can't be determined, the source is held out of retrieval and flagged
  **"needs tags"** until completed.
- **Dedupe** — identical or same-name re-uploads supersede the older copy (keep latest).
- **Background ingestion** with a live **progress indicator** (queued → processing → ready).
- **Stewardship write-back** — *"add this to the KB"* creates a reviewable task; approving it
  writes the wiki page, appends the Revision log + `_CHANGELOG.md`, **commits to git**, and
  re-indexes.

**Deliverables**
- **Excel export** — any answer containing a table (e.g. a Salesforce → Customer 360 +
  Consent Extension source-to-target mapping) has a **Download Excel** button that produces a
  formatted `.xlsx` (one sheet per table).

**Governance & admin** (Filament panel at `/admin`)
- Roles: **Viewer · Contributor · Steward · Admin**.
- Resources: Stewardship queue, Knowledge Sources (upload / edit metadata / re-ingest /
  delete / ingestion status), Wiki Pages, Users, Audit Log.
- Encrypted-at-rest Anthropic API key with a Test button.

---

## Architecture

| Part | Stack | Responsibility |
|---|---|---|
| `api/` | **Laravel** (PHP 8.4) · **Filament v4** · **Sanctum** · **PostgreSQL + pgvector** | REST + SSE API, auth/roles, vendor-isolated retrieval, queued ingestion, admin/governance panel, Claude via **Prism**, `.xlsx` export (PhpSpreadsheet) |
| `web/` | **Next.js 16** · React 19 · Tailwind v4 · `lucide-react` | Three-pane chat UI (Conversations · Chat · Source Inspector) with OKLCH design tokens and SSE streaming |
| `kb/` | Plain Markdown (git-backed) | The knowledge: `wiki/` (curated), `raw/` (uploaded sources), `output/` (exports + Q&A log) |
| `embeddings/` | Python **FastAPI** sidecar (optional) | Local embeddings (`BAAI/bge-large-en-v1.5`, 1024-d) — an offline alternative to the Voyage AI API |

**Vendor isolation.** Every chunk carries `mdm_vendor`, `data_platform`, `financial_model`,
`domain`, `scope`, and (for uploads) `product` / `product_version`. A conversation's locked
stack drives a SQL filter on every search; vendor-neutral content (foundations, governance,
GDPR) is shared across all stacks.

**Embeddings.** `EMBEDDINGS_DRIVER` = `fake` (deterministic, offline dev — not semantic),
`sidecar` (local Python, recommended for real local retrieval), or `voyage` (hosted API).
After switching drivers, re-index with `php artisan kb:ingest --fresh`.

---

## Repo layout

```
api/          Laravel API — controllers, services (Kb ingestion, Retrieval, Chat),
              jobs (IngestUploadedFile/IngestUrlSource/ApplyStewardshipTask), Filament panel
web/          Next.js app — components (ChatArea, Sidebar, Inspector, UploadModal, …), lib/
kb/           Knowledge base — wiki/ (curated) · raw/ (sources) · output/ · MANIFEST.md
embeddings/   Optional Python embeddings sidecar (FastAPI + sentence-transformers)
scripts/      dev-local.sh — (re)starts all local services
docker-compose.yml   Postgres+pgvector · api · worker · scheduler · web · sidecar · proxy
```

---

## Local setup

**Prerequisites:** PHP 8.4 + Composer · Node 18+ · PostgreSQL 16+ with the **pgvector**
extension. Optional: Python 3 (embeddings sidecar) · **poppler** + **tesseract** (PDF text +
OCR — `brew install poppler tesseract`).

```bash
# 1. Postgres + pgvector: create db `mdm`, then enable the extension
psql -d mdm -c 'CREATE EXTENSION IF NOT EXISTS vector;'

# 2. API
cd api
cp .env.example .env            # set DB_*; pick EMBEDDINGS_DRIVER (fake|sidecar|voyage)
composer install
php artisan key:generate
php artisan migrate --seed      # seeds roles + admin/steward users
php artisan kb:ingest           # index kb/wiki + kb/raw into pgvector
php artisan serve               # REST + SSE API
php artisan queue:work          # (separate shell) processes uploads/ingestion/stewardship

# 3. Embeddings sidecar (optional, for real semantic retrieval)
cd ../embeddings && python3 -m venv .venv && ./.venv/bin/pip install -r requirements.txt
./.venv/bin/uvicorn app:app --port 8001
#   then set EMBEDDINGS_DRIVER=sidecar in api/.env and run `php artisan kb:ingest --fresh`

# 4. Web
cd ../web
echo 'NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api' > .env.local   # point at your API
npm install && npm run dev      # http://localhost:3000
```

Sign in with the seeded **admin@canyondatainsights.com / password**, open **Settings**, and
paste a Claude API key (Test, then save) to enable generated answers. Retrieval, uploads, and
Excel export work without a key; only token generation needs one.

> **macOS convenience:** [`scripts/dev-local.sh`](scripts/dev-local.sh) (re)starts every
> service at once — Postgres, the embeddings sidecar, the API (with raised upload limits),
> the queue worker, and the web app. It's tailored to a local Herd + Homebrew setup; override
> `PHP_BIN` / `PG_BIN` / `PGDATA` as needed.

### Notes
- **A queue worker must be running** for uploads/ingestion to process (`php artisan queue:work`).
- Uploaded source **binaries** (PDF/DOCX/…) are git-ignored — kept local, not committed.
- Per-file upload size is bounded by PHP's `upload_max_filesize` / `post_max_size`; the dev
  script raises them to 128M / 160M.

---

## Docker (hosting)

```bash
docker compose up --build   # postgres+pgvector, api, queue worker, scheduler, web, sidecar, proxy
```

See [`docker-compose.yml`](docker-compose.yml). Target a long-running host with a persistent
volume — the app writes files (KB enrichment, uploads) and keeps an on-disk vector index, so
it is **not** suited to read-only serverless platforms.

---

## Documentation

- KB operating contract: [`kb/MANIFEST.md`](kb/MANIFEST.md) · topic index: [`kb/_INDEX.md`](kb/_INDEX.md) · changes: [`kb/_CHANGELOG.md`](kb/_CHANGELOG.md)
- API endpoints live under `/api` (auth, conversations, messages SSE, sources, uploads,
  exports, stewardship, settings, meta); the governance UI is at `/admin`.
