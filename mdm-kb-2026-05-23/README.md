# MDM Knowledge Base

A living technical library covering Informatica MDM, Informatica Data Quality (IDQ/CDQ), modern data platform integration (Databricks, Snowflake), and data governance with consent/privacy law alignment. The voice throughout is that of a senior technical data architect — assume the reader is technically literate, wants the *why* alongside the *how*, and will push back if the guidance is sloppy.

## Folder layout

```
mdm-kb/
├── README.md                ← you are here
├── _INDEX.md                ← topic-to-file map; the table of contents
├── _CHANGELOG.md            ← every change to the wiki, dated and reasoned
├── raw/                     ← canonical source material, untouched
│   ├── informatica-mdm/
│   ├── informatica-dq/
│   ├── databricks/
│   ├── snowflake/
│   ├── governance-gdpr/
│   └── uploads/             ← drop new material here before asking me to ingest
├── wiki/                    ← the queryable knowledge; my source of truth at chat time
│   ├── 01-foundations/
│   ├── 02-informatica-mdm/
│   ├── 03-data-quality/
│   ├── 04-pipelines-medallion/
│   ├── 05-snowflake/
│   ├── 06-databricks/
│   ├── 07-governance-consent/
│   ├── 08-patterns-playbooks/
│   └── 09-decisions-adrs/   ← architecture decision records: what we chose and why
└── output/                  ← exports (docx, pdf) generated on request
```

## How this stays alive

This knowledge base does not auto-refresh in the background. It refreshes when *you* drive it. The refresh loop is:

1. **You upload raw material** to `raw/uploads/` (PDFs, docs, release notes, internal designs) and tell me to ingest it. I summarize the substance, file the original under the right `raw/` subfolder, and update the relevant wiki page(s) with a dated note in the page's *Revision log*.
2. **You ask me to refresh a topic** ("pull the latest on Informatica C360 SaaS"). I web-search, compare to what the wiki says today, update the page, and log the change in `_CHANGELOG.md`.
3. **We have a Q&A that produces something worth keeping** (a clarification, a decision, a pattern that worked). You say *"capture this to the wiki"* and I write it into the relevant page or open a new one. ADRs (decisions with rationale) go under `wiki/09-decisions-adrs/`.

Every wiki page ends with a **Revision log** so the lineage of the knowledge is visible. The voice is opinionated where the field has a defensible best answer, and explicit about trade-offs where it doesn't.

## Querying the knowledge base in chat

In any new chat in this project, you can ask things like:

- *"What does the wiki say about Informatica match rule tuning?"*
- *"Summarize the GDPR right-to-erasure page and tell me how it changes my survivorship design."*
- *"Compare the Snowflake DMF approach to the DLT expectations approach for the silver layer."*
- *"Open ADR-001 and remind me what we decided about Databricks vs Snowflake for the cleanse layer."*

I'll read the relevant wiki files and answer from them first; my training data is the fallback when the wiki is silent. If I think the wiki is stale, I'll say so and offer to refresh.

## Adding new raw material

Three options, in order of preference:

1. **Upload a file in chat** and say "add this to the KB". I'll move it to the right `raw/` subfolder, summarize, and update the relevant wiki page(s).
2. **Paste a URL** and say "ingest this". I'll fetch, summarize, save the summary to `raw/`, and update the wiki.
3. **Type the content directly** ("here's how our team handles X — capture it"). I'll write it into the wiki and tag it as internal knowledge so it's distinguishable from vendor-published material.

## Architect's voice — what to expect

- I'll tell you when a vendor's marketing answer differs from the engineering reality.
- I'll flag where Informatica's docs are version-specific and brittle (10.4 vs 10.5 vs the SaaS / IDMC line — they are *not* the same product).
- I'll separate "this is the spec" from "this is what works in practice."
- I won't pretend a topic is settled when it isn't (e.g., where to do address verification — in IDQ before MDM, or in the MDM cleanse step — is a legitimate trade-off, not a one-right-answer question).
