# Handoff — MDM Knowledge Hub

A snapshot of where the build stands so any session (including a fresh Claude Code
session in VS Code) can resume without re-deriving context. The full approved design
is in [`PLAN.md`](./PLAN.md).

## What this is

A multi-user, vendor-isolated MDM knowledge platform: a governed Markdown knowledge
base (`kb/`) + a Laravel API (`api/`) + a Next.js chat UI (`web/`). Users lock a
technology stack (e.g. Informatica + Databricks + Customer) and the assistant answers
**only within that stack, with citations** — Informatica/SAP/Profisee/Reltio/Ataccama
and Databricks/Snowflake are never mixed.

## Repo layout

```
api/          Laravel 13 API: PostgreSQL/pgvector, Sanctum auth, Prism (Claude),
              ingestion (kb:ingest), retrieval w/ isolation, Filament installed
web/          Next.js 16 + React 19 + Tailwind v4: three-pane UI (design handoff)
kb/           knowledge base: wiki/ (30 pages) + raw/ + output/ + MANIFEST/_INDEX
embeddings/   optional Python FastAPI sidecar (sentence-transformers, local)
docs/         this handoff + the approved PLAN.md
docker-compose.yml, Caddyfile   deployment path for a long-running host
```

## Status — built & verified (Phase 1)

- **KB reorganized** into `kb/` (wiki/raw/output); 30 pages ingest into **369 chunks**.
- **Schema** (Postgres + pgvector): conversations, messages, sources, wiki_pages,
  chunks (vector + isolation metadata + HNSW), stewardship_tasks, audit_log, settings.
- **Ingestion** (`php artisan kb:ingest`): front-matter/path metadata, heading-aware
  chunking, pluggable embeddings (fake/voyage/sidecar), idempotent. ✅ verified.
- **Vendor isolation**: SQL filter — a chunk is eligible only if untagged on a
  dimension or matching the locked stack. ✅ verified: Informatica+Databricks returned
  **zero** Snowflake/other-vendor chunks; Snowflake-locked returned zero Databricks.
- **API**: Sanctum auth + roles, conversation CRUD with immutable locked stack,
  **SSE chat** (Prism → Claude, meta/delta/done/error), uploads → `kb/raw` → ingest,
  sources list/inspector, stewardship approve/reject, admin settings, meta/stats.
  ✅ verified via curl incl. CORS preflight from the web origin.
- **Admin API-key UI**: encrypted-at-rest key, masked hint, save + test. ✅ verified
  (store/mask/validate).
- **Frontend**: full three-pane port (Sidebar / ChatArea / Inspector), Stack-Lock
  modal, Settings modal, Browse modal (sources/stats/stewardship), login, SSE
  streaming + citation chips. ✅ typechecks, production-builds, serves (HTTP 200).

> Not exercised here: live Claude token generation (needs a real `ANTHROPIC_API_KEY`,
> set via the Settings UI). Everything up to the model call is verified.

## Run locally

1. **Postgres + pgvector**: db `mdm`, role `mdm`, `CREATE EXTENSION vector;`
2. **API**: `cd api && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed && php artisan kb:ingest && php artisan serve`
3. **Web**: `cd web && npm install && npm run dev` → http://localhost:3000
4. Sign in (`admin@canyondatainsights.com` / `password`), open **Settings**, paste a
   Claude API key, **Test**, save. Default embeddings driver is `fake` (offline);
   set `EMBEDDINGS_DRIVER=voyage` (+ `VOYAGE_API_KEY`) or `sidecar` for real retrieval.

Docker path: `docker compose up --build` (needs a Docker daemon).

## Resuming in VS Code

```bash
git clone https://github.com/canyondatainsights/mdm.git
cd mdm && git checkout claude/elegant-galileo-iguwA && code .
```

Install the Claude Code extension (or run `claude` in the terminal). A new session
won't have this chat's transcript — point it at this file and `docs/PLAN.md`.

## What's next (Phase 2, per PLAN.md)

- Stewardship **write-back loop**: apply approved diff → page Revision log +
  `_CHANGELOG.md` → git commit → re-index (queued job). Currently tasks are created
  and approved/rejected; the file application is the remaining step.
- Content: `kb/wiki/10-financial-data-models/` (ISDA CDM, FpML) + healthcare/HIPAA;
  flesh out SAP/Profisee/Reltio/Ataccama sections (structure + tags already supported).
- Filament admin **resources** (users, sources, audit log views) — Filament is
  installed; panel provider exists at `/admin`, resources not yet generated.
- DOCX/XLSX/PPTX parsing in ingestion (PDF/MD/TXT done); optional `web_refresh`
  freshness job; per-block structured assistant messages (callout/options) to fully
  match the design's richer block types.
- SSO (Entra/Google) to replace email/password; production app server
  (Octane/FrankenPHP) instead of `artisan serve`.

## Conventions

- KB stays portable plain Markdown (`kb/MANIFEST.md` is the operating contract).
- Isolation metadata is derived from wiki section/path or front-matter
  (`vendor`/`platform`/`financial_model`/`domain`/`scope`) — see
  `api/app/Services/Kb/Metadata.php`.
- Add a vendor: drop pages under a tagged section (or set front-matter) and extend
  `config/mdm.php` `dimensions`.
