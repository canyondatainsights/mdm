---
title: "What is Medallion Architecture? | Databricks"
mdm_vendor: databricks
data_platform: databricks
scope: neutral
---

# What is Medallion Architecture? | Databricks

Medallion architecture is a data design pattern for logically organizing data in a lakehouse so that quality and structure improve progressively as data moves through successive layers. Often called a **multi-hop architecture**, it defines three distinct zones — Bronze, Silver, and Gold — each serving a different purpose and audience. Databricks treats this pattern as a first-class design approach for Lakehouse pipelines built on Delta Lake.

---

## The Three Layers

### Bronze — Raw Data

The Bronze layer is the landing zone for all inbound data from external source systems.

- Table structures mirror the source system schema **as-is**, with no transformation.
- Additional metadata columns are appended at ingest time (e.g., load timestamp, process/job ID, source system identifier).
- Primary goals are **Change Data Capture (CDC)**, historical archiving, data lineage, auditability, and the ability to reprocess data without re-reading from the source.
- Acts as cold storage; optimized for write throughput and completeness over query performance.

### Silver — Cleansed and Conformed Data

The Silver layer applies "just-enough" cleansing and standardization to produce an **enterprise view** of key business entities.

- Data from multiple Bronze sources is matched, merged, deduped, and conformed (e.g., master customer records, canonical store records, deduplicated transactions, cross-reference tables).
- Follows an **ELT** rather than ETL philosophy — minimal, carefully scoped transformations are applied; heavy business logic is deferred to the Gold layer.
- Data models tend toward **3rd Normal Form (3NF)**; Data Vault-style write-performant models are also appropriate here.
- Primary consumers: Departmental Analysts, Data Engineers, and Data Scientists performing ad-hoc reporting, advanced analytics, and ML feature development.

### Gold — Curated, Business-Level Tables

The Gold layer holds consumption-ready, project-specific datasets optimized for reporting and decision-making.

- Data models are **de-normalized and read-optimized** (fewer joins), following **Kimball star-schema** or **Inmon data-mart** conventions.
- Final data transformations and the strictest data-quality rules are applied at this layer.
- Organized into project- or domain-specific databases, for example:
  - Customer Analytics
  - Product Quality Analytics
  - Inventory Analytics
  - Customer Segmentation & Recommendations
  - Marketing / Sales Analytics
- Enables **pan-EDW analytics** by combining data from traditional RDBMS data marts and EDWs — workloads that were cost-prohibitive on legacy stacks (e.g., joining IoT/manufacturing data with sales data for defect analysis, or merging clinical EMR data with financial claims for healthcare insights).

---

## Layer Comparison

| Attribute | Bronze | Silver | Gold |
|---|---|---|---|
| Data state | Raw, as-is | Cleansed, conformed | Aggregated, consumption-ready |
| Schema style | Source-native + metadata | 3NF / Data Vault | Star schema / Data Mart |
| Transformation depth | None | Minimal ("just-enough") | Full business logic |
| Primary consumers | Data Engineers | Analysts, Data Scientists | Business users, BI tools, ML models |
| Key concerns | Completeness, lineage, replayability | Deduplication, conformance, self-service | Performance, readability, governance |

---

## Building Pipelines on Databricks

Databricks provides first-class tooling to implement medallion pipelines:

- **Lakeflow / Spark Declarative Pipelines** — define Bronze, Silver, and Gold tables with minimal code; the framework handles dependency resolution and incremental execution.
- **Streaming tables** — backed by Apache Spark Structured Streaming, these tables are incrementally updated as new data arrives.
- **Materialized views** — automatically recomputed when upstream data changes, suitable for Silver and Gold aggregations.
- Streaming tables and materialized views can be **combined in a single pipeline**, enabling mixed batch-and-streaming architectures within the same medallion DAG.
- Delta Lake underpins all layers, providing **ACID transactions**, **time travel**, and schema enforcement throughout.

---

## Key Benefits

- **Simple, understandable model** — clear entry points for different teams and skill levels.
- **Incremental ETL** — only changed data is reprocessed at each hop, reducing compute cost.
- **Replayability** — Gold and Silver tables can be fully rebuilt from Bronze at any time without touching source systems.
- **Governance and auditability** — raw data is preserved in Bronze; lineage is traceable across all hops.
- **Reusability** — a single Bronze or Silver table can feed multiple downstream Gold tables (one-to-many fan-out).
- **Data mesh compatibility** — the layered topology maps naturally onto domain-oriented data mesh ownership, with Bronze/Silver tables shared across domains and Gold tables owned by individual product teams.

---

## Relationship to the Lakehouse

A lakehouse combines the scalability and openness of a data lake with the reliability and performance of a data warehouse. Medallion architecture is the recommended organizational pattern within a lakehouse: it breaks data silos, enforces progressive quality gates, and provides secure, governed access to both raw and curated data on a single platform. Traditional RDBMS data marts and EDWs can be ingested into the Bronze layer, making cross-system analytics possible in a way that was previously cost-prohibitive.

*Source: [www.databricks.com](https://www.databricks.com/blog/what-is-medallion-architecture)*

## Revision log

| Date | Change |
|---|---|
| 2026-05-24 | Authored via admin. |

