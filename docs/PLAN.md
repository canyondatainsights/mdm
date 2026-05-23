# MDM Knowledge Base — Hybrid Platform (Laravel API + Next.js UI)

## Context

`canyondatainsights/mdm` already holds a real, 30-page Informatica-centric knowledge base (on `origin/main` under `mdm-kb-2026-05-23/`) written in a senior-data-architect voice, governed by a `MANIFEST.md` operating contract (cite pages, wiki-first, surface staleness, update workflow, ADRs, changelog). It also holds a **high-fidelity design handoff** (`design_handoff_mdm_knowledge_base/`) — a three-pane chat UI (Conversations sidebar · Chat thread · Source Inspector) specified in React/JSX with OKLCH tokens, Inter + JetBrains Mono, Lucide icons, citation chips, and an Excerpt/Lineage/Related inspector.

This plan builds the **application layer**: a multi-user, HTTP-served platform that lets users chat against the KB with citations, upload/ingest new material, and enrich the KB over time — with **hard vendor isolation** (a user locks their stack up front and answers never mix vendors), **domain scoping** (customer, product, vendor, supplier, finance, healthcare), and extensibility to other MDM vendors (Profisee, Reltio, Ataccama, SAP) and financial data models (ISDA CDM, FpML).

### Stack (confirmed): Hybrid — Laravel API backend + Next.js frontend
- **Backend (`api/`, PHP/Laravel):** REST + SSE endpoints; **PostgreSQL + `pgvector`** as the single datastore (users, conversations, audit, AND the vector index); **Filament** admin panel for the governance surface (users/roles, sources, wiki pages, stewardship queue, audit log, admin settings); Laravel **queues + scheduler** for ingestion and freshness; **Prism** (prism-php) — or a direct call to the Anthropic Messages API — for Claude with streaming + tool use; embeddings via **Voyage AI** API (default) or an optional local **Python sidecar** (sentence-transformers) for offline/free.
- **Frontend (`web/`, Next.js + TS):** pixel-perfect port of the design handoff (Tailwind with the OKLCH tokens as CSS variables, `next/font` for Inter + JetBrains Mono, `lucide-react`), consuming the Laravel API; SSE for streamed assistant replies.
- **Knowledge base (`kb/`):** the existing `mdm-kb-2026-05-23/` content, moved to a stable `kb/` (`kb/wiki`, `kb/raw`, `kb/output`), git-backed, ingested into pgvector.

Why this shape: Filament delivers the multi-user/governance/admin half (a large part of the product) cheaply; pgvector makes **vendor isolation a simple, auditable SQL filter**; Next.js lets us reuse the React design directly for the bespoke streaming chat. Cost: two services to run, and embeddings come from an API/sidecar (no in-process local model in PHP).

---

## Architecture

### Data model (PostgreSQL + pgvector)
- `users`, `roles`, `permissions` (spatie/laravel-permission): roles ≈ Viewer, Contributor, Steward, Admin.
- `conversations` — owner, title, **locked stack** (`mdm_vendor`, `data_platform`, `financial_model`, `domains[]`), `pii_redacted`, timestamps. The lock is immutable for the life of the conversation.
- `messages` — role, structured `content` blocks (p/ol/callout/options per the design), `citations` (json), `confidence`, model, token usage.
- `sources` — raw material metadata (title, doc type PDF/DOCX/XLSX/PPTX/Confluence/MD, owner, pages, tags, vendor/platform/domain tags, `approved` flag, git path under `kb/raw/`).
- `wiki_pages` — path under `kb/wiki/`, section, vendor/platform/domain/scope tags, `updated_at` (from Revision log) for **staleness**.
- `chunks` — `embedding vector(1024)` (Voyage) **+ isolation metadata columns** `mdm_vendor`, `data_platform`, `financial_model`, `domain`, `scope` (`vendor-specific|neutral`), `source_path`, `anchor`, `content_hash`. HNSW index on `embedding`; btree on the metadata columns.
- `stewardship_tasks` — proposed enrichments/ADRs (target path, diff, summary, proposer, status pending/approved/rejected). Powers the design's **Stewardship queue**.
- `audit_log` — every write (who/what/when), mirrored to git commits.
- `settings` — encrypted (`encrypted` cast) Anthropic key + model/config.

### Vendor isolation (the hard requirement) — enforced at retrieval
Every chunk carries stack metadata. A conversation's locked stack drives a SQL filter on every search, so cross-vendor / cross-platform chunks are **physically excluded from the model's context** (defense in depth: the system prompt also forbids mixing):
```sql
SELECT ... FROM chunks
WHERE (mdm_vendor = :locked_vendor OR scope = 'neutral')
  AND (data_platform = :locked_platform OR scope = 'neutral')
  AND (financial_model = :locked_fin OR financial_model IS NULL OR scope = 'neutral')
  AND domain = ANY(:locked_domains)
ORDER BY embedding <=> :query_vec
LIMIT :k;
```
Informatica vs SAP/Profisee/Reltio/Ataccama and Databricks vs Snowflake are never mixed. Neutral content (foundations, governance, GDPR) is shared across stacks. Domains: customer, product, vendor, supplier, finance, healthcare.

### Chat flow (Laravel API, SSE)
`POST /api/conversations/:id/messages` → embed query (Voyage/sidecar) → pgvector search with the isolation filter → assemble cited context + the MANIFEST senior-architect system prompt (with prompt caching on the stable prefix) → stream Claude via Prism with tool use → emit assistant blocks + citations over **SSE** → persist message + append the turn to `kb/output/qa-log/`. Citations resolve to `kb/wiki/...` paths and open in the Inspector.

### Enrichment / "improve over time" (default: stewardship-gated)
Claude exposes confirm-gated tools (`propose_wiki_edit`, `create_adr`, `save_to_raw`, `search_kb`). On the MANIFEST trigger phrases (*"capture this to the wiki"*, *"add this to the KB"*, *"refresh this topic"*, *"ingest this file"*), a write tool creates a **stewardship_task** (a proposed diff) rather than writing directly. A Steward/Admin reviews it in the Stewardship queue; on approval the backend writes the wiki file, appends the page **Revision log** + `_CHANGELOG.md`, **commits to git** (attributed to the user — this is the audit trail), and re-indexes the affected chunks. This unifies the design's Stewardship queue + Audit log + the MANIFEST update workflow and keeps the shared corpus trustworthy under multi-user writes.

### Ingestion (artisan command + queued jobs)
`php artisan kb:ingest` walks `kb/wiki` + `kb/raw`: parse by type (PDF/DOCX/XLSX/PPTX/MD via PHP libs; the Python sidecar can assist with hard PDFs) → heading/paragraph-aware chunking (~800–1000 tokens, ~15% overlap) → embed → upsert into `chunks` with isolation metadata (derived from front-matter + path). `content_hash` makes re-ingest idempotent. Uploads (Filament or chat "Attach") land in `kb/raw/<category>/` and enqueue the same job.

### Freshness
Laravel scheduler re-checks page `updated_at` vs a staleness threshold (the chat flags stale fast-moving topics, e.g. IDMC) and can enqueue an optional `web_refresh` job (network-policy dependent; Phase 2).

---

## Repo layout (monorepo)
```
/ (repo root)
  api/                 Laravel app: Filament admin, REST+SSE, Eloquent, jobs, scheduler,
                       Prism, pgvector integration, kb:ingest command
  web/                 Next.js app: three-pane UI port (Tailwind tokens, next/font, lucide-react)
  kb/                  knowledge base (moved from mdm-kb-2026-05-23/)
    MANIFEST.md _INDEX.md _CHANGELOG.md README.md export-kb.sh
    wiki/ (01..09 existing + 10-financial-data-models/ new)
    raw/{informatica-mdm,informatica-dq,databricks,snowflake,governance-gdpr,
         financial-models,healthcare,uploads}/
    output/            qa-log/, generated exports
  embeddings/          (optional) Python FastAPI sidecar for local embeddings
  docker-compose.yml   postgres+pgvector, api, queue worker, scheduler, web, sidecar, Caddy/nginx TLS
  README.md            run/deploy instructions
```

---

## Design port specifics (web/)
- Tokens → `:root` CSS variables (exact OKLCH values from the handoff) + Tailwind theme; Inter + JetBrains Mono via `next/font`; icons via `lucide-react` (handoff provides the name→Lucide mapping).
- Components: `Sidebar` (280px, color wash, brand mark, New conversation, search, 5-item nav rail, pinned/dated conversation rows with color rails + domain tag pills, user footer) · `ChatArea` (52px header, 760px-max thread, day separator, user bubble, assistant message with p/ol/callout/options blocks, citation chips, Sources block, toolbar) · `Composer` (textarea, Attach, **Stack Lock chip**, Filters, char counter, send) · `Inspector` (380px, Excerpt with trust-score bars + highlighted phrase, Lineage diagram, Related). Loading = 3-dot typing indicator; add error/retry pill and focus-visible ring (handoff calls these out as missing).
- The composer "Domain · Customer" chip becomes the **Stack Lock** control: on new conversation the user must pick MDM vendor + data platform (+ optional financial model + domains); locked dims render as the header pills. Desktop-first (≥1280px) per handoff; Inspector/Sidebar collapse at smaller widths.
- Nav rail wiring: Ask the hub = chat; Knowledge sources = sources list; Data model explorer = domain/vendor model browser; Stewardship queue = enrichment approvals; Audit log = git/changelog history.

---

## KB content changes
- Move `mdm-kb-2026-05-23/` → `kb/`; remove the stray top-level duplicate `.md` files; create the missing `kb/raw/*` and `kb/output/` (with `.gitkeep`); add `kb/export-kb.sh` if absent.
- Add front-matter tags to every wiki page (`vendor`, `platform`, `financial_model`, `domain`, `scope`) so ingestion can populate isolation metadata. Existing Informatica pages → `mdm_vendor: informatica`; Snowflake/Databricks pages → respective `data_platform`; foundations/governance/GDPR → `scope: neutral`.
- Add **`kb/wiki/10-financial-data-models/`** (ISDA CDM, FpML, and how they map to MDM party/product/reference domains) and seed **healthcare** domain governance (HIPAA alongside GDPR). Authoring approach for financial/healthcare content to be confirmed (see Defaulted decisions).
- Scaffold vendor sections for SAP / Profisee / Reltio / Ataccama (structure + tags), fleshed out as content/sources arrive.

## API contract (Laravel, consumed by Next.js)
`GET /api/conversations`, `GET/POST /api/conversations`, `GET /api/conversations/:id/messages`, `POST /api/conversations/:id/messages` (SSE stream), `GET /api/sources/:id`, `POST /api/uploads`, `POST /api/stewardship/tasks` + approve/reject, `POST /api/jira/tickets` (optional/Phase 2). Auth via Laravel **Sanctum** (SPA token) — email/password to start, swappable to Entra/Google SSO later.

---

## Defaulted decisions (flagged — tell me to change any)
1. **Auth:** Sanctum email/password now; SSO (Entra/Google) later.
2. **Embeddings:** Voyage AI API by default; Python sidecar available for fully-local.
3. **Enrichment governance:** stewardship review-queue (not instant writes), matching the design.
4. **API key:** admin-only Filament settings page, stored encrypted in Postgres, with a Test button + masked display (supersedes the earlier flat-file approach now that we have a DB).

---

## Verification (end-to-end)
1. `docker-compose up` brings up Postgres+pgvector, api, worker, scheduler, web (+ sidecar). `php artisan migrate` + `php artisan kb:ingest` populates `chunks`; re-run confirms idempotent skip via `content_hash`.
2. Hit the API with curl: create a conversation locked to **Informatica + Databricks + Customer**, ask *"How do I cleanse and validate customer data before loading the MDM hub?"* → streamed architect-voice answer citing `kb/wiki/...`. **Confirm zero Snowflake/SAP content appears** (isolation). Repeat locked to Snowflake to confirm the inverse.
3. In `web/`, verify the three-pane UI renders to the design (tokens, fonts, icons, citation chips open the Inspector, Sources block, Stack Lock pills in the header).
4. Upload a PDF → lands in `kb/raw/uploads/`, ingests, becomes retrievable in the locked stack only.
5. Say *"capture this to the wiki"* → a stewardship_task appears in the queue; approve as Steward → wiki file + Revision log + `_CHANGELOG.md` updated, git commit created, chunks re-indexed; reject leaves files untouched.
6. Admin settings: set/test/mask the Anthropic key (encrypted in DB); chat fails gracefully with a clear message when unset.

I'll run docker-compose, migrations, `kb:ingest`, and exercise the API via curl + logs, and boot `web/` to check rendering. Full browser click-through may be limited here; I'll state what was verified programmatically vs. what you should confirm visually.

---

## Top risks & mitigations
1. **Two-service complexity (Laravel + Next.js).** Mitigate with docker-compose for one-command local up; Sanctum + CORS configured once; clear API contract.
2. **SSE streaming through PHP/Prism.** Validate the streamed token path early (route + `text/event-stream`); fall back to chunked responses if needed.
3. **Embeddings dependency (Voyage key/network).** Provide the Python sidecar as the offline fallback; keep embedding provider behind one config.
4. **Vendor-isolation correctness** (the critical requirement). Filter at SQL level + reinforce in system prompt; add an automated test asserting no off-stack chunk is ever returned for a locked conversation.
5. **Multi-user write integrity.** Stewardship queue + git-commit-per-approval + serialized writes; roles gate who can approve.
6. **PDF/Office extraction quality.** Validate per format; sidecar/OCR for hard/scanned files (Phase 2).
7. **API-key security.** Encrypted at rest, admin-only, never returned/logged, masked in UI.

---

## Phasing
- **Phase 1:** monorepo + docker-compose; Postgres/pgvector schema + migrations; move KB to `kb/` + tag content + ingestion; Laravel API (auth, conversations, messages/SSE, sources, uploads) with **vendor isolation**; Claude via Prism; Next.js three-pane UI port with Stack Lock + citations + Inspector; Filament admin (users, sources, settings/API key); Q&A logging. Working end-to-end product.
- **Phase 2:** stewardship write-back loop (propose→approve→commit→reindex) and Audit log views; `10-financial-data-models/` (CDM/FpML) + healthcare content; SAP/Profisee/Reltio/Ataccama sections; Data model explorer; optional `web_refresh` freshness job, Jira action, SSO, OCR.

All work commits to `claude/elegant-galileo-iguwA` (after merging `origin/main`) and pushes `-u origin`. No PR unless requested.
