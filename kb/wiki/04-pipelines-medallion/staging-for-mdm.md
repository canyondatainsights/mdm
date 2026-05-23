# Staging for MDM — Mapping Medallion to Informatica

This is the bridge page. The medallion architecture (bronze/silver/gold) and Informatica MDM's data flow (landing/staging/base-object) are two different vocabularies for overlapping concerns. Programs that don't reconcile them end up duplicating cleansing work in two places.

The architect's prescription:

```
Source                Lake / Warehouse                    Informatica MDM
systems     →    Bronze    Silver         →     Landing → Staging → Base Object → BVT
                 (raw)    (cleansed)            (raw       (cleanse  (master    (golden)
                                                 to MDM)    + map)    record)
```

## The handoff: silver → landing

Silver is where data quality work happens for *everyone*, not just MDM. Landing is where MDM takes ownership.

What silver delivers to landing:

- **Cleansed values.** Trimmed, case-normalized, formatted to standard form (phones in E.164, dates in ISO 8601, emails lowercased).
- **Validated records.** Records that failed silver-layer validation have been routed to quarantine. Landing receives only records that passed silver's contract.
- **Deduplicated within source.** If a source-system dump has 1000 exact duplicate rows, silver collapses them to one. MDM still does cross-source match-merge.
- **Conformed schema.** Column names and types match what landing expects.
- **Source attribution.** Records carry a source-system identifier so MDM staging knows which staging table to route to. (Or you have separate silver tables per source, which is cleaner.)
- **Effective timestamp.** When the record was current in the source, not just when it landed in bronze.

What silver doesn't deliver (MDM staging handles these):

- **Address verification.** Usually done as part of MDM staging cleanse, because AV licensing is colocated with MDM in many shops. But this is a defensible split — if your AV is silver-side, that's fine; the rule is *one* canonical place, not both.
- **MDM-specific match key generation.** Soundex, metaphone, normalized match strings tuned for the SSAName3 engine. These are MDM-internal concerns and don't belong in silver.
- **Cross-source merging.** The whole point of MDM. Silver does not attempt this.

## The handoff: BVT → gold

After MDM resolves the golden record, the BVT is published downstream. The two patterns:

**Materialization.** A scheduled job replicates the BVT view into a gold-layer table in the warehouse. Consumers query gold, not MDM. Latency depends on the schedule (typically hourly or nightly).

**Real-time API.** Consumers query MDM directly via BES (Business Entity Services). Lower latency but ties consumers to MDM's availability and throughput.

For analytics and BI: materialize into gold. For operational systems that need fresh data: BES.

The gold table is *not* a copy of the BVT — it's a consumer-shaped view of MDM data. The MDM ROWID isn't needed in gold; consumer-friendly customer IDs are. The XREF lineage isn't needed in gold; just the consolidated attributes.

## The two competing philosophies — explicit

You will see these two camps argue. The architect's view is that both work; the choice is contextual.

**Philosophy A: Clean in silver. MDM passes through.**

- Silver does all cleansing, standardization, validation.
- MDM staging cleanse step is minimal — just match-key generation and AV if AV lives in MDM.
- Trust: silver is the engineering team's authority.
- Pro: cleansing logic in version-controlled pipeline code (Python, SQL). Testable. Reusable.
- Pro: non-MDM consumers (BI, ML) benefit from the same cleansed data.
- Con: silver team and MDM team must align on standards (case, format, abbreviation policy). Without alignment, silver might case-normalize names while MDM's match rules expected raw case — match quality drops silently.

**Philosophy B: Land raw. MDM cleanses inside the hub.**

- Silver does general cleansing but treats MDM as a special consumer.
- MDM staging cleanse step does heavy lifting — IDQ-published web services called per record.
- Trust: MDM team is the authority on customer/supplier/product data.
- Pro: end-to-end MDM data quality is one team's responsibility.
- Pro: the licensed cleansing tooling (AV, name parser) stays in one place.
- Con: cleansing logic is harder to test and version. The Hub Console (on-prem) is not a CI/CD environment.
- Con: non-MDM consumers re-implement cleansing or accept slightly different data.

Most mature programs settle on a *split based on cleansing type*:

- **General cleansing** (trim, case, format, deduplication, validation against universal patterns) — silver.
- **MDM-specific cleansing** (match-key generation, name parsing tuned for SSAName3) — MDM staging.
- **Address verification** — wherever you have AV licensed. Often MDM staging in legacy programs, often silver in newer ones.

Whatever you decide, **make the decision explicit and write it in an ADR**. Otherwise the silver and MDM cleansing logic will drift apart over time as different teams make different changes.

## What landing tables should look like

A landing table is a mirror of one source's extract for one domain. Practical structure:

| Column | Type | Purpose |
|---|---|---|
| PKEY_SRC_OBJECT | VARCHAR | Source primary key. MDM tracks this. |
| SOURCE_SYSTEM | VARCHAR | Source identifier. Same value for all rows of this landing table. |
| Business columns | various | The actual data. Same shape as silver. |
| EXTRACT_TIMESTAMP | TIMESTAMP | When this record was current in the source. |
| LOAD_TIMESTAMP | TIMESTAMP | When this row landed in MDM. |
| SOURCE_ROW_HASH | VARCHAR | Hash of business columns for change detection. |

The source row hash is the trick that makes delta detection cheap. Don't compare every column on every load to decide what changed; just hash and compare hashes.

## What staging tables should look like

Staging adds:

- **Match key columns.** Computed values used only for matching.
- **Validation flags.** Columns indicating which validation rules this record failed (for downstream stewardship).
- **Cleanse-derived columns.** Standardized versions of business columns.

The staging table is per (source × base object). Landing-to-staging mapping configures the transformation.

## Common questions

**Q: Can I skip the landing table and point MDM directly at silver?**

A: Yes, technically. You can configure MDM to consume from an external table or view. The trade-off is auditability — you lose a snapshot of "what MDM saw at load time". If your silver is well-versioned (Delta time travel, Snowflake Time Travel) you can reconstruct, but it's not as clean as an immutable landing snapshot. Pragmatic: for high-volume sources, skip the physical landing table; for low-volume critical sources, keep it.

**Q: Where does CDC happen?**

A: CDC at the source feeds bronze. Bronze-to-silver can use Delta CDF or Snowflake Streams to incrementally process changes. Silver-to-landing can also be incremental (only changed records since last MDM load). MDM staging then handles incremental load via delta detection (the source row hash trick).

**Q: What about real-time updates?**

A: Real-time customer creation typically bypasses the medallion altogether — the application calls BES, MDM creates the record, MDM publishes events. Real-time is a separate architecture from the batch medallion. They coexist: real-time for create/update at the moment of action, batch for bulk reconciliation and recurring sources.

## Sources

- docs.informatica.com — *About Landing Tables*, *MDM Hub Staging Overview*.
- Architect's accumulated practice from real implementations.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
