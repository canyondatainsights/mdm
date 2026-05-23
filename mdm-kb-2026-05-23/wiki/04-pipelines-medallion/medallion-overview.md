# Medallion Architecture — Overview

Medallion architecture is the bronze/silver/gold pattern popularized by Databricks. It is a layered approach to data quality and processing: each layer represents a higher level of refinement, and data flows through them in a defined order. It's not new — the concept is older than the naming — but the name has stuck because it gives engineers a shared vocabulary.

This page is the architect's take on what each layer is for, what mistakes to avoid, and how it maps to MDM. The platform-specific implementations (Snowflake DMFs, Databricks DLT) are in their own pages.

## The three layers

**Bronze — raw arrival.** Data as it came from the source. Append-only, schema-on-read where possible, immutable once landed. Minimal-to-no transformation. The audit trail of *what arrived*.

**Silver — cleansed and conformed.** Data that has passed validation, been standardized to canonical formats, and been deduplicated within a source. Schema-enforced. Joinable across sources. Where data quality is enforced and the data is *trustworthy* for general use.

**Gold — business-ready.** Aggregated, denormalized, modeled for specific consumption — dashboards, ML features, downstream apps. Optimized for query patterns of consumers.

A useful mental model: bronze is the *audit*, silver is the *truth*, gold is the *product*.

## What goes in each layer

| Bronze | Silver | Gold |
|---|---|---|
| Raw API responses, file dumps, CDC change feeds | Cleansed entities (customers, orders, products) | Aggregates, dimensional models, feature tables |
| Original schema preserved | Conformed schema with type enforcement | Business-friendly schema, often denormalized |
| Ingestion metadata (source, timestamp, file name) | Quality flags, lineage to bronze | Pre-computed metrics, sliced and diced |
| All records, including bad ones | Only records that passed validation; bad records to quarantine | Records aggregated to business level |
| Latency-optimized writes | Quality-optimized writes | Query-optimized reads |
| Append-only | Upsert/merge OK | Frequent rebuild or incremental refresh |
| Days to weeks retention typically | Long retention; the analytic core | Retention varies; some long, some short |

## The strict rules (don't break them)

These get violated constantly. The violations cause weeks of debugging later. Don't.

**Rule 1: Bronze does not transform.** It ingests. Casting strings to native types is acceptable; reshaping, standardizing, or filtering is not. If you transform in bronze, you lose the audit. The whole point of bronze is to be able to answer "what did we receive?" — if you've already cleansed before persisting, you can't.

**Rule 2: Silver enforces schema and quality.** Records that fail go to quarantine, not into silver. Silver is the layer where consumers (gold pipelines, MDM, BI, ML training) should be able to assume the data is good. If silver has bad data, the contract is broken.

**Rule 3: Gold is consumer-shaped.** A gold table exists to serve a specific use case. If two consumers need different shapes, write two gold tables. Don't try to make a universal gold table that serves everyone — you'll end up with a table that serves no one well.

**Rule 4: Quality gates between layers, not within.** Cleansing and validation happen at layer transitions (bronze → silver, silver → gold). Within a layer, you don't have to re-validate. This keeps the responsibility model clear.

**Rule 5: Layers are about quality, not just storage.** It's tempting to add layers (raw → bronze → silver → gold → platinum) because each transformation step seems to deserve a layer. Resist. More layers means more places for errors to creep in and more maintenance. Three layers is plenty for almost all use cases.

## Common anti-patterns

**Bronze that's actually silver.** Cleansing has crept into bronze. The bronze tables look pristine but you've lost the ability to investigate ingestion issues. Fix: split — keep a true-raw landing and rename the cleansed table to silver.

**Silver that's actually bronze.** No quality enforcement. Records flow through silver unchecked. Gold consumers find themselves filtering and validating, which means silver isn't doing its job. Fix: add expectations/DMFs at the silver layer; quarantine failures.

**Gold tables that no one consumes.** Built speculatively. Have all the right aggregations for a dashboard that never got built. Fix: only build gold for committed consumers. Empty gold is technical debt.

**Catalog/schema chaos.** Bronze, silver, gold scattered across multiple catalogs or schemas with inconsistent naming. Fix: a clear schema convention — `<domain>_bronze`, `<domain>_silver`, `<domain>_gold` or three schemas per environment (`bronze`, `silver`, `gold`) with tables namespaced by domain.

**The "for the lake" trap.** Treating the medallion layers as a generic lake and forgetting that consumers exist. Each gold table should have a consumer (a dashboard, a downstream system, an ML model) and a contract.

## Mapping to MDM

A worked-out version is in [`staging-for-mdm.md`](staging-for-mdm.md). The short answer:

- **Bronze** ↔ raw arrival from source systems. Pre-MDM. Equivalent in concept to a raw landing zone.
- **Silver** ↔ cleansed, conformed, deduplicated-within-source data. This is what feeds Informatica MDM's *landing tables*. Most of the cleansing the silver layer does is what would otherwise be done in MDM's staging cleanse step. Doing it in silver is reusable beyond MDM.
- **Gold** ↔ consumption layer. Built *from* MDM, not feeding into it. The BVT from MDM is replicated/materialized into gold tables for analytics and downstream consumption.

So MDM is not a layer in the medallion architecture. MDM is a *system* that consumes from silver and produces into gold. The match-merge-survivorship work that MDM does is genuinely different from the validate-and-conform work that silver does — they don't compete.

## Streaming vs batch in medallion

The pattern works the same way for both. Streaming sources land continuously into bronze; silver and gold can be streaming continuations or scheduled materializations. Databricks Auto Loader + DLT streaming tables handle this natively. Snowpipe + Streams + Tasks is the Snowflake equivalent.

The general advice: don't use streaming where batch is fine. Streaming has higher operational complexity, higher cost (continuous compute), and tighter constraints on logic (you can't easily re-process the past from a streaming pipeline). Use streaming when the business actually needs sub-minute freshness; use batch otherwise.

For MDM specifically: most MDM use cases are fine with hourly or daily batch. Real-time MDM exists (and BES API enables it) but it's a different architecture conversation.

## Sources

- docs.databricks.com — *What is the medallion lakehouse architecture?*
- Databricks Community: *The Medallion Architecture: Why Data Layers Matter for Modern Lakehouses*.
- dev.to: *Medallion Architecture in Databricks: A Complete Implementation Guide* (referenced for production-pattern detail).
- Tacnode: *Medallion Architecture: Bronze, Silver and Gold Layers in Modern Lakehouses*.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
