# Sidecar

**Sidecar** is a vendor-isolated, chat-queryable knowledge platform for data management &
governance — Master Data Management (Informatica, SAP, Reltio, …), data quality, platform docs
(Databricks/Snowflake), governance & consent, financial data models, and **data privacy**
(GDPR/CCPA + MDM × privacy). It pairs a governed Markdown knowledge base with a senior-architect
LLM assistant that answers **with citations**, **never mixes vendors**, and can **generate
deliverables** (e.g. source-to-target mapping spreadsheets) — fronted by a polished Next.js chat
UI and a Filament admin back office.

> The knowledge content lives in [`kb/`](kb/) as portable plain Markdown. The application layer
> (`api/` + `web/`) is additive — the `kb/` tree stays readable and transferable on its own
> (see [`kb/MANIFEST.md`](kb/MANIFEST.md)).

---

## Features

### Chat & retrieval (web app)
- **Stack-locked conversations** — each conversation locks an MDM vendor + data platform
  (+ optional financial model, subjects/domains, and industry extensions). Retrieval
  **hard-filters in SQL**, so answers can never mix Informatica with SAP/Reltio/…, or Databricks
  with Snowflake. Vendor-neutral content (foundations, governance, **data privacy**) is shared.
- **Cited answers** grounded in the KB; each citation names the **original source** (URL, file,
  or wiki page) with product/version/date and opens in the **Source Inspector** (trust score,
  visual lineage, clickable origin).
- **Semantic retrieval** over pgvector; **conversation memory**; a **stop button**; and
  **dynamic suggested questions** generated from the locked stack + the last exchange.
- **Rich rendering** of answers and wiki pages: headings, **GFM tables**, nested lists,
  blockquotes, links/images, **syntax-highlighted code** (highlight.js) and **Mermaid diagrams**.
- **Browse knowledge** — a sidebar entry opens a full-pane **wiki reader** (section list + a
  roomy reader with an "On this page" table of contents).
- **Excel export** — any answer containing tables gets a **Download Excel** button → a formatted,
  multi-tab `.xlsx` (one colored tab per target entity).

### Conversations & research (web app)
- **Auto-titled conversations** — each thread is titled from its first exchange (LLM, with a
  question-based fallback), so the sidebar reads as real topics instead of "New conversation".
- **Pin & rename** — pin important threads (grouped under **Pinned**) and rename them inline;
  conversations are per-user.
- **Research topics** — save topics for later deep-dives (title + notes + an optional locked stack),
  scoped **Private** or **Shared ("group research")** so a team can collaborate. **Deep-dive**
  launches a new conversation pre-locked to the topic's stack with the composer seeded.
- **Share links** — opt-in, **revocable public** read-only links to a conversation, with
  **Copy / Email / Teams** actions and a branded **Open Graph** preview image for nice Teams/email
  unfurls. The shared transcript page is read-only and `noindex`.

### Wiki authoring (admin)
- **Create & view wiki pages** directly in the admin — write Markdown, embed **images/diagrams**
  (stored in the versioned KB), and save to write → ingest → git-commit in one step.
- **Draft with AI** — generate a structured page from the title + tags (uses Mermaid for flows).
- **Import from URL** — fetch a page's readable content **and images**, optionally cleaned up by
  the AI, into the editor with a source-attribution footer.

### Knowledge expansion (Steward/Admin)
- **Upload PDF / Markdown / TXT / scripts (.sql, .py, .json, …), or a reference URL**, from the
  admin or the chat composer. Scanned/scan-only PDFs fall back to **OCR** (poppler + tesseract).
- **Auto-classification** — an LLM classifier proposes vendor · product · version · subject ·
  extension for review; unclassifiable sources are held out of retrieval and flagged *needs tags*.
- **Approval gating, dedupe, background ingestion** with a live progress indicator.
- **Documentation crawlers** — sitemap-driven crawlers that fetch, **convert to structured
  Markdown**, classify by URL path, and ingest a site (e.g. docs.databricks.com, docs.snowflake.com).
  Crawlers can be **platform-specific** or **topic/subject** (neutral, e.g. *data-privacy* over
  gdpr-info.eu + cppa.ca.gov), run ad-hoc or on a **per-crawler schedule**.
- **Stewardship write-back** — *"add this to the KB"* in chat creates a reviewable task; approving
  it authors the wiki page, updates `_CHANGELOG.md`, **commits to git**, and re-indexes.

### Admin back office (Filament panel at `/admin`)
- **Cobalt-themed** console (distinct from the coral chat app) with the Sidecar brand.
- **Dashboard** — KB stat cards, vendor-depth & subject-depth charts, and an open steward-requests table.
- **Resources** — Knowledge Sources (upload / edit metadata / re-ingest / approve / delete),
  **Wiki Pages** (author/view), **Crawlers** (manage + run + schedule), **Taxonomy**
  (runtime-editable vendors/platforms/subjects/products — add a subject like `data-privacy`
  with no deploy), Stewardship Queue, Users, Audit Log.
- **Pages** — **AI Settings** (set the Claude API key + pick the model), **Re-classify** (scoped,
  queued LLM re-tagging + a CLI guide), and **About** (live stack versions).
- **Idle auto-logout** — 15 min on the admin, 30 min on the web app.
- Roles: **Viewer · Contributor · Steward · Admin**.

---

## Architecture

| Part | Stack | Responsibility |
|---|---|---|
| `api/` | **Laravel 13** (PHP 8.4) · **Filament v4** · **Sanctum** · **PostgreSQL + pgvector** | REST + SSE API, auth/roles, vendor-isolated retrieval, queued ingestion + crawlers, admin/governance panel, Claude via **Prism**, `.xlsx` export (PhpSpreadsheet) |
| `web/` | **Next.js 16** · React 19 · Tailwind v4 · `lucide-react` · `highlight.js` · `mermaid` | Three-pane chat UI (Conversations · Chat · Source Inspector) + wiki reader, OKLCH design tokens, SSE streaming |
| `kb/` | Plain Markdown (git-backed) | The knowledge: `wiki/` (curated/authored) · `raw/` (uploaded + crawled sources) · `output/` (exports + Q&A log) |
| `embeddings/` | Python **FastAPI** sidecar | Local embeddings (`BAAI/bge-large-en-v1.5`, 1024-d) — no API rate limits |

**Vendor isolation.** Every chunk carries `mdm_vendor`, `data_platform`, `financial_model`,
`domain`, `extension`, `scope`, and `product`/`product_version`. A conversation's locked stack
drives a SQL filter on every search; `scope=neutral` content is shared across all stacks.

**AI.** Claude via Prism; the **model and API key are managed in the admin** (AI Settings,
DB-backed and encrypted). **Embeddings** via the local sidecar (default), `voyage`, or `fake`
(offline dev). After switching embedding drivers, re-index with `php artisan kb:ingest --fresh`.

---

## Repo layout

```
api/          Laravel API — services (Kb ingestion/crawl/retrieval, Chat, Taxonomy),
              jobs (IngestUploadedFile/IngestUrlSource/ApplyStewardshipTask/ReclassifyKb),
              Filament panel (Resources + Pages + Widgets)
web/          Next.js app — components (ChatArea, Sidebar, Inspector, WikiBrowser, Markdown, …)
kb/           Knowledge base — wiki/ (curated) · raw/ (sources) · output/ · MANIFEST.md
embeddings/   Python embeddings sidecar (FastAPI + sentence-transformers)
scripts/      dev-local.sh — (re)starts all local services
docker-compose.yml   Postgres+pgvector · api · worker · scheduler · web · sidecar · proxy
```

### Useful artisan commands
```
php artisan kb:ingest [--fresh]        # index kb/wiki + kb/raw into pgvector
php artisan kb:crawl <key> [--dry-run] # run a documentation crawler (sitemap → ingest)
php artisan crawlers:run-scheduled     # run crawlers whose schedule is due (registered hourly)
php artisan kb:refetch-urls [--platform=] [--limit=]  # re-fetch URL sources with current extraction
php artisan kb:reclassify [--dry-run] [--only=] [--limit=] [--revert]  # LLM re-tagging
php artisan taxonomy:fetch-products <vendor>   # LLM-fetch a vendor's product list
```

---

## Local setup

**Prerequisites:** PHP 8.4 + Composer · Node 18+ · PostgreSQL 16+ with **pgvector**. Optional:
Python 3 (embeddings sidecar) · **poppler** + **tesseract** (PDF text + OCR —
`brew install poppler tesseract`).

```bash
# 1. Postgres + pgvector: create db `mdm`, then enable the extension
psql -d mdm -c 'CREATE EXTENSION IF NOT EXISTS vector;'

# 2. API
cd api
cp .env.example .env            # set DB_*; pick EMBEDDINGS_DRIVER (fake|sidecar|voyage)
composer install
php artisan key:generate
php artisan migrate --seed      # seeds roles + admin/steward users + taxonomy
php artisan kb:ingest           # index kb/wiki + kb/raw into pgvector
php artisan serve               # REST + SSE API
php artisan queue:work          # (separate shell) processes uploads/ingestion/crawlers
php artisan schedule:work       # (separate shell) runs scheduled crawlers

# 3. Embeddings sidecar (recommended for real semantic retrieval)
cd ../embeddings && python3 -m venv .venv && ./.venv/bin/pip install -r requirements.txt
./.venv/bin/uvicorn app:app --port 8001
#   set EMBEDDINGS_DRIVER=sidecar in api/.env, then `php artisan kb:ingest --fresh`

# 4. Web
cd ../web
echo 'NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api' > .env.local   # point at your API
npm install && npm run dev      # http://localhost:3000
```

Sign in with the seeded **admin@canyondatainsights.com / password**, open **`/admin` → AI
Settings**, and paste a Claude API key + pick a model to enable generated answers. Retrieval,
uploads, and Excel export work without a key; only token generation needs one.

> **macOS convenience:** [`scripts/dev-local.sh`](scripts/dev-local.sh) (re)starts every service
> at once — Postgres (:5433), the embeddings sidecar (:8001), the API (:8011, raised upload
> limits), a self-healing queue worker + scheduler, and the web app (:3000). Tailored to a local
> Herd + Homebrew setup; override `PHP_BIN` / `PG_BIN` / `PGDATA` as needed. (It uses :8011, not
> :8000 — set `NEXT_PUBLIC_API_URL` accordingly.)

### Notes
- **A queue worker must be running** for uploads/ingestion/crawls to process. It caches code —
  **restart it after deploying** code changes.
- Uploaded/crawled `kb/raw` content (PDF/DOCX/… **and** `.md/.txt/.html`) is git-ignored — kept
  local, not committed. Authored `kb/wiki` pages **are** version-controlled.
- Per-file upload size is bounded by PHP's `upload_max_filesize` / `post_max_size`; the dev
  script raises them.

---

## Docker (hosting)

```bash
docker compose up --build   # postgres+pgvector, api, queue worker, scheduler, web, sidecar, proxy
```

See [`docker-compose.yml`](docker-compose.yml). Target a long-running host with a persistent
volume — the app writes files (KB enrichment, uploads, crawled pages) and keeps an on-disk vector
index, so it is **not** suited to read-only serverless platforms.

---

## Documentation

- KB operating contract: [`kb/MANIFEST.md`](kb/MANIFEST.md) · topic index: [`kb/_INDEX.md`](kb/_INDEX.md) · changes: [`kb/_CHANGELOG.md`](kb/_CHANGELOG.md)
- API endpoints under `/api` (auth, conversations [+ pin/rename/share], messages SSE, public
  `share/{token}`, sources, **wiki**, **research-topics**, uploads, exports, stewardship, settings,
  meta); the admin/governance UI is at `/admin`.
