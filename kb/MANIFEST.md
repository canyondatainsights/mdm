# MANIFEST — How to use this Knowledge Base

This file is the entry point for any system or person picking up this knowledge base. If you are an AI assistant (Claude, GPT, Gemini, or otherwise) and a user has handed you this folder, **read this file first** before answering any question or making any changes.

---

## What this is

A living technical knowledge base on:

- **Informatica MDM** — Multidomain MDM (on-prem 10.x), MDM SaaS, Customer 360, Supplier 360, Product 360, Reference 360.
- **Informatica Data Quality** — IDQ on-prem and Cloud Data Quality (CDQ) in IDMC. Profiling, cleansing, standardization, address verification, rule design.
- **Data pipeline architecture** — Medallion (bronze/silver/gold) on Databricks and Snowflake. How to stage, cleanse, normalize, and validate data before it reaches MDM.
- **Data governance and consent** — GDPR, CCPA/CPRA, and other privacy regimes; consent management; right-to-erasure handling inside an MDM (the hardest problem in this space).

It is structured for both human reading and programmatic ingestion.

---

## Format and portability

- **Every file is plain UTF-8 Markdown.** No proprietary formats, no embedded binaries in the wiki. Renders in any Markdown viewer, parses with any text tool, diffs cleanly in Git.
- **No external dependencies.** No links to private systems, no required runtime, no required database. The folder tree on its own is fully self-contained.
- **Stable folder layout** (see *Structure* below). The numeric prefixes (`01-`, `02-`, ...) are deliberate — they keep ordering predictable across filesystems and version control.
- **Vendor-agnostic.** The KB is hosted on Anthropic's Claude today; nothing in the content or structure assumes that. It can be loaded into any AI assistant, indexed by any RAG system, or read by a human with a text editor.

### Transferring to another system

The knowledge base is transferred as a single tarball or zip:

```bash
tar -czf mdm-kb-YYYY-MM-DD.tar.gz mdm-kb/
# or
zip -r mdm-kb-YYYY-MM-DD.zip mdm-kb/
```

Drop that archive anywhere — another Claude project, a ChatGPT custom GPT's knowledge files, a Notion import, a Confluence space, a Git repo, an Obsidian vault, a local RAG index. The content travels because it's just text.

Ask the host system to generate a fresh archive any time by saying *"export the KB"* or *"give me a transferable archive of the knowledge base"*.

---

## Structure

```
mdm-kb/
├── README.md                 ← human entry point; explains the project
├── MANIFEST.md               ← this file; AI/system entry point
├── _INDEX.md                 ← topic-to-file map (table of contents)
├── _CHANGELOG.md             ← every change across the KB, dated
├── raw/                      ← canonical source material (immutable)
│   ├── informatica-mdm/
│   ├── informatica-dq/
│   ├── databricks/
│   ├── snowflake/
│   ├── governance-gdpr/
│   └── uploads/              ← incoming user material, pre-triage
├── wiki/                     ← the curated knowledge (mutable, versioned)
│   ├── 01-foundations/
│   ├── 02-informatica-mdm/
│   ├── 03-data-quality/
│   ├── 04-pipelines-medallion/
│   ├── 05-snowflake/
│   ├── 06-databricks/
│   ├── 07-governance-consent/
│   ├── 08-patterns-playbooks/
│   └── 09-decisions-adrs/    ← architecture decision records
└── output/                   ← generated exports (docx, pdf, archives)
```

### Conventions

- **Wiki pages** are the source of truth at query time. They synthesize raw material plus accumulated Q&A insight.
- **Raw files** are never edited after they land — they are the audit trail. If something in raw is wrong, the correction goes in the wiki, with a note.
- **ADRs** (`wiki/09-decisions-adrs/ADR-NNN-*.md`) record decisions and the reasoning behind them. Once written, an ADR is never edited — superseded ADRs get a new ADR that references and replaces the old.
- **Every wiki page ends with a *Revision log*** so the history of that specific page is visible in-place.

---

## How a future AI assistant should operate this KB

When a user opens a conversation in any system that has access to this KB, the assistant should:

1. **Adopt the voice**: senior technical data architect. Opinionated where the field has a defensible best answer; explicit about trade-offs where it doesn't. Don't soften technical reality. Don't pretend a vendor's marketing claim is settled engineering truth.
2. **Read wiki files first, training data second.** The wiki is the user's curated truth. If the wiki and your training data disagree, the wiki wins — but flag the disagreement so the user can adjudicate.
3. **Cite the page** when answering. *"From `wiki/02-informatica-mdm/match-merge-survivorship.md`: ..."* is the expected pattern. The user should always be able to trace an answer back to a file.
4. **Surface staleness honestly.** If a wiki page's Revision log shows the last update was a year ago and the user is asking about a fast-moving product (especially Informatica IDMC, which ships features regularly), say so and offer to refresh via web search.
5. **Update the wiki when the user says so.** Triggers: *"capture this to the wiki"*, *"add this to the KB"*, *"refresh this topic"*, *"ingest this file"*. Always update the page's *Revision log* and append an entry to `_CHANGELOG.md`.
6. **Write ADRs for decisions.** When the user makes an architectural choice with reasoning ("we'll do cleansing in Databricks not IDQ because..."), offer to write an ADR. Use the template in `wiki/09-decisions-adrs/_template.md`.
7. **Don't fabricate.** If a topic isn't in the wiki and you're not confident from training data, say so and offer to research. Inventing detail in a knowledge base is the worst possible failure mode — it pollutes the source of truth.

### Update workflow

```
User uploads/pastes new material
        ↓
File the original under raw/<category>/
        ↓
Identify which wiki page(s) it informs
        ↓
Update those pages with the new substance
        ↓
Append entry to each page's Revision log
        ↓
Append entry to _CHANGELOG.md
        ↓
If the change reflects a decision: write an ADR
```

---

## Versioning and provenance

- The KB has no semantic version on its own; `_CHANGELOG.md` is the version history.
- The current state of any page is the page as it exists in the file. The page's *Revision log* shows what changed and when.
- Source attribution lives at the bottom of each page in a *Sources* section: URLs, uploaded file paths, or "internal — from Q&A with user on YYYY-MM-DD".
- When in doubt about a fact, the chain is: page body → page Sources → `raw/` material → external web search (and update the page if findings warrant).

---

## License and data sensitivity

- The wiki content is original synthesis; quotations from vendor docs are short and attributed.
- Raw material in `raw/` may include third-party documentation — keep it for internal reference only and don't redistribute outside its license terms.
- If a user uploads internal/proprietary documents (architecture diagrams, design specs), they live in `raw/uploads/` and are treated as confidential. The wiki summarizes them without leaking sensitive specifics unless the user has explicitly cleared the content for inclusion.

---

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial manifest, v1.0. Establishes portability contract and AI-assistant operating instructions. |
