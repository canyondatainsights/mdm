# Data Model: Landing → Staging → Base Object → BVT

This page documents Informatica MDM's canonical data flow. The terms here apply directly to Multidomain MDM 10.x (on-prem). MDM SaaS abstracts these layers behind business-entity configuration, but the underlying processing model is conceptually the same — and understanding the on-prem model makes the SaaS abstractions easier to reason about.

## The flow at a glance

```
Source Systems
     │
     ▼
[Landing Table]            ← raw arrival from upstream pipeline (one per source-table)
     │
     │   Stage process applies cleanse functions, decomposition, validation
     ▼
[Staging Table]            ← one per (source × base-object); cleansed, source-tagged
     │
     │   Load process inserts/updates the base object
     ▼
[Base Object Table]        ← consolidated master; each row is a record
     │
     │   Tokenization → Match → Merge → Survivorship
     ▼
[Base Object — BVT]        ← Best Version of the Truth, the golden record
```

Plus supporting structures, not optional:

- **Cross-reference (XREF)** — one row per source contribution to the base object. The lineage of the golden record.
- **History** — every version of the base object record, if history is enabled.
- **Reject** — records that failed staging validation.
- **Match candidates** — pairs of records flagged by the match process for merge consideration.

## Landing tables

The landing table is the contract between the upstream pipeline (typically a Snowflake/Databricks/PowerCenter/CDI job) and Informatica MDM. Once data is in landing, MDM owns it.

Key facts:

- **Schema is up to you.** Informatica doesn't dictate the landing table's structure beyond a primary key. It mirrors whatever your source-system extract produces.
- **One source system can populate multiple landing tables.** You don't pack everything into one wide table.
- **One landing table can receive data from multiple sources** — but you usually don't do this, because you lose source attribution.
- **No transformation happens at landing.** It's a drop zone.
- **The pipeline is external to MDM.** MDM doesn't pull from sources; sources (or your medallion pipeline) push to landing.

Practical pattern in a modern stack: your silver-layer Delta or Snowflake table is materialized into the landing table via a CDI mapping or external table reference. Some teams skip the physical landing table by pointing Informatica directly at a view over silver. That works but loses some auditability — you can't replay a historical landing snapshot if the silver table moves on.

## Staging tables

The staging table is where the work happens. Each staging table is tied to **one source system and one base object**. If you have a Customer base object loaded from CRM, ERP, and Marketing Cloud, you have three staging tables, all feeding the same base object.

The landing-to-staging mapping is where you configure:

- **Column mappings** — landing column → staging column, with optional cleanse function applied in flight.
- **Cleanse functions** — built-in (trim, upper, regex), IDQ-published as web services, or custom user exits. Applied per column.
- **Decomposition / aggregation** — splitting one landing column into multiple staging columns (e.g., full name → first, middle, last) or combining multiple into one.
- **Filtering** — drop rows that match a filter expression. Use sparingly; usually better to route to a reject table than silently drop.
- **Primary key mapping** — single column or composite. The PKEY_SRC_OBJECT is how MDM tracks the source record across loads.
- **Delta detection** — comparing the new landing row to the last loaded version of the same PKEY to decide whether to insert, update, or no-op.
- **Audit trail** — capturing the timestamp and changes for each staging insert/update.

The most common mistake here is doing too much cleansing inside Informatica's cleanse functions when the data should have been cleaned upstream in the silver layer. There are two defensible philosophies:

1. **Clean upstream (silver) and pass through staging.** Staging cleanse functions are minimal — formatting only. Pro: clean data is reusable beyond MDM. Con: silver and MDM must coordinate on standards.
2. **Receive raw at landing, do all MDM-specific cleansing in staging.** Pro: MDM owns its data quality end-to-end. Con: cleansing logic gets reimplemented for downstream consumers.

The team's actual practice usually ends up between these — silver does general cleansing (trim, case, basic format), staging does MDM-specific work (address verification for matching, name parsing for matching). The decision belongs in an ADR.

## Base object tables

The base object is the persistent home of master records. Each row is a record (in MDM lingo: a "candidate for the golden record" before merge, or "a contribution" after merge). The same business entity may have multiple rows on the base object until match-merge consolidates them.

Each base object row has:

- **ROWID_OBJECT** — surrogate primary key, MDM-internal. Never expose externally.
- **The business columns** — first name, last name, address, etc.
- **Trust columns** (if trust is enabled on the base object) — one per trust-enabled business column, holding the current trust score for that cell.
- **Validation columns** (if validation rules are enabled) — flagging cells that fail validation.
- **History columns** — last update timestamp, last update source, etc.
- **Consolidation indicator** — where this row is in the match-merge lifecycle.
- **State** — for soft-delete and state-management (PENDING / ACTIVE / DELETED).

The **BVT view** of a base object is what most consumers see: the merged, survived golden record, with each cell potentially sourced from a different XREF contribution.

## XREF — the lineage

For every base-object record, the XREF table records every source record that contributed. Columns include:

- ROWID_OBJECT (which base object record this contributes to)
- PKEY_SRC_OBJECT (the source system's primary key)
- ROWID_SYSTEM (which source system)
- The original column values from this source

XREF is what makes "the golden record" auditable. Without XREF, you have a record and no way to defend why. With XREF, you can show every steward and auditor: this came from CRM, that came from ERP, this column survived because the trust score from ERP was higher, here's the timestamp.

**Never delete from XREF lightly.** Even when a source goes away. The XREF history is the legal defense if anyone asks why the golden record looks the way it does. The right move for retired sources is to mark them inactive, not to delete.

## How MDM SaaS abstracts this

In MDM SaaS, the configuration UI is *business entity* oriented (Customer, Supplier) rather than table-oriented. You define the business entity model, smart fields, AI matching, and the platform handles the physical storage. The landing/staging/base-object structure still exists underneath but you don't configure each layer manually. This is genuinely simpler for typical projects but harder to debug when something goes wrong — you have less visibility into where a record is in the lifecycle.

For new projects on SaaS, learning the on-prem model first is still worth the time. It tells you what the SaaS abstractions are hiding.

## Sources

- docs.informatica.com — Multidomain MDM 10.4 / 10.5 Configuration Guide: *About Landing Tables*, *MDM Hub Staging Overview*, *Entity Base Object Example*, *Cell Data Survivorship and Order of Precedence*.
- Informatica MDM Concepts tutorial material (course content, referenced for terminology).

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
