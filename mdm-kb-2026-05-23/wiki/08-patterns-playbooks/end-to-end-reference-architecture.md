# End-to-End Reference Architecture

A worked reference. Take this as a defensible starting design that synthesizes the patterns from this wiki into a coherent whole. Real implementations diverge from it in specific ways for specific reasons — document the divergences in ADRs.

## The whole picture

```
                         ┌────────────────────────────────────────────┐
                         │     SOURCE SYSTEMS                         │
                         │  CRM    ERP    Marketing   Web/Mobile      │
                         │   │      │       │            │            │
                         └───┴──────┴───────┴────────────┴────────────┘
                             │      │       │            │
                             ▼      ▼       ▼            ▼
                         ┌────────────────────────────────────────────┐
                         │     INGESTION                              │
                         │  Files to S3/ADLS/GCS                      │
                         │  CDC streams (Kafka / Debezium)            │
                         │  Real-time API calls (BES write-through)   │
                         └────────────────────────────────────────────┘
                                              │
                                              ▼
       ┌──────────────────────────────────────────────────────────────────────┐
       │     LAKEHOUSE / WAREHOUSE — Medallion                                │
       │                                                                      │
       │  ┌─────────┐    ┌──────────────────┐    ┌──────────────────────┐    │
       │  │ BRONZE  │ →  │ SILVER           │ →  │ GOLD                 │    │
       │  │         │    │ • cleansed       │    │ • consumer-shaped    │    │
       │  │ raw     │    │ • validated      │    │ • BVT materialized   │    │
       │  │ append- │    │ • dedup'd        │    │ • aggregates         │    │
       │  │ only    │    │ • DQ enforced    │    │ • ML features        │    │
       │  └─────────┘    └──────────────────┘    └──────────────────────┘    │
       │                          │                       ▲                  │
       └──────────────────────────┼───────────────────────┼──────────────────┘
                                  │                       │
                                  ▼                       │
       ┌──────────────────────────────────────────────────────────────────────┐
       │     INFORMATICA MDM                                                  │
       │                                                                      │
       │  ┌──────────┐   ┌──────────┐   ┌──────────┐                          │
       │  │ Landing  │ → │ Staging  │ → │ Base Obj │ → BVT view → ─────────►  │
       │  │          │   │ +cleanse │   │ +match   │                          │
       │  │          │   │ (AV etc) │   │ +merge   │                          │
       │  │          │   │          │   │ +survive │                          │
       │  └──────────┘   └──────────┘   └──────────┘                          │
       │                                       │                              │
       │                                       ▼                              │
       │                                  ┌─────────┐                         │
       │                                  │ Stewards│                         │
       │                                  │ (UI)    │                         │
       │                                  └─────────┘                         │
       └──────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
       ┌──────────────────────────────────────────────────────────────────────┐
       │     SYNDICATION & CONSUMPTION                                        │
       │                                                                      │
       │   Real-time (BES)    Batch (gold replicas)    Events (Kafka)         │
       │       │                    │                       │                 │
       │       ▼                    ▼                       ▼                 │
       │   Apps, portals      BI tools, ML training     Downstream apps       │
       │                                                                      │
       └──────────────────────────────────────────────────────────────────────┘

       ┌──────────────────────────────────────────────────────────────────────┐
       │     CROSS-CUTTING                                                    │
       │   • Reference 360 (code sets, crosswalks, hierarchies)               │
       │   • CMP / Consent service (consent capture, withdrawal)              │
       │   • Catalog / Lineage (Unity Catalog, Snowflake tags, Collibra)      │
       │   • Orchestration (Workflows, Airflow, Dagster)                      │
       │   • Monitoring (DQ scorecards, pipeline observability)               │
       │   • Identity / SSO (steward and consumer auth)                       │
       └──────────────────────────────────────────────────────────────────────┘
```

## The flow narrated

**Sources arrive.** CRM nightly extracts to S3; ERP CDC streams via Kafka; the customer signup flow on the web calls BES directly to create a Party in real time. Three integration patterns, each fit to the latency and volume of its source.

**Bronze.** Files landed in S3 are picked up by Auto Loader (Databricks) or Snowpipe (Snowflake) into bronze tables. Schema evolution handled by the ingestion layer; rescued data captured for unknown columns. Bronze is append-only — every record that arrived is there forever (subject to retention policy).

**Silver.** Scheduled jobs (DLT pipelines on Databricks; Streams+Tasks on Snowflake) cleanse bronze into silver. Trim, case-normalize, validate, deduplicate within source. DQ expectations (DLT) or DMFs (Snowflake) enforce minimum quality; records that fail go to quarantine, not silver. Address verification called from silver where AV is licensed silver-side. Reference data joined from Reference 360.

**MDM landing.** Informatica CDI maps silver tables into MDM landing tables. One landing table per (source × domain). Source attribution preserved.

**MDM staging.** MDM's staging cleanse step performs MDM-specific work — match key generation, name parsing tuned for SSAName3, address verification if AV is staging-side. Records that fail staging-level validation route to MDM's reject tables.

**MDM match/merge/survivorship.** The match engine identifies candidate pairs. Above the auto-merge threshold, records are automatically consolidated. Between thresholds, queued for steward review. Survivorship determines which cell wins, with trust scores, source ranking, and block survivorship configured per column.

**BVT.** The consolidated Best Version of the Truth. Available via the BES API for real-time consumers and via materialization into gold for batch consumers.

**Gold.** Periodic jobs materialize the BVT (and supporting structures — Party-to-Source crosswalk, hierarchy flattenings) into the warehouse gold layer. Gold tables are consumer-shaped — denormalized, business-friendly column names, with reference data pre-joined.

**Consumption.** Real-time consumers (the customer-facing app showing a 360 view) call BES. Batch consumers (BI dashboards, ML training, reverse-ETL) read from gold. Event-driven consumers (the marketing platform that needs to be notified when a customer record changes) subscribe to MDM-published events.

## Critical cross-cutting elements

**Reference 360** is invoked throughout. Silver-layer cleansing uses reference data for valid-value validation. MDM staging joins reference data for code translation. Gold pre-joins for consumer convenience. Versioning and effective-dating are critical — reference data changes affect interpretation of historical records.

**Consent service** is parallel to MDM. Whether implemented as a dedicated CMP or as a child entity of the customer in MDM (see [`../07-governance-consent/consent-management.md`](../07-governance-consent/consent-management.md)), consent state must flow with the customer to every consumer system.

**Lineage and catalog** capture the metadata. Unity Catalog (Databricks) or equivalent gives column-level lineage from bronze through silver to landing. XREF gives lineage within MDM. The full path — "this BVT cell came from this XREF contribution, which came from this MDM landing row, which came from this silver row, which came from this bronze file" — should be reconstructable.

**Orchestration** ties it together. The end-to-end pipeline (source → bronze → silver → MDM landing → MDM staging/match/merge → gold materialization → downstream notification) is a single orchestrated workflow with dependencies and SLAs.

**Monitoring** is across all layers. DQ scorecards on silver and gold; pipeline observability for the orchestration; stewardship queue metrics for MDM; consumer-facing SLAs for the publishing channels.

## Where the design varies

- **Real-time vs batch dominance.** A real-time-heavy program leans on BES, Kafka events, and the consent service. A batch-heavy program leans on the materialized gold layer.
- **Single MDM hub vs federated.** Smaller programs run one hub. Programs with data residency obligations (GDPR, PIPL) may need regional hubs reconciled at the metadata layer.
- **Cleansing split.** The split between silver-side and MDM-side cleansing is the most-debated architectural choice. The ADR for the chosen split should be the first one written.
- **AV placement.** Wherever the AV license sits, the pipeline calls it there. Document.
- **MDM platform.** On-prem Multidomain MDM 10.x vs MDM SaaS — different cleansing customization paths, different operational characteristics.
- **Lake vs warehouse.** Snowflake vs Databricks vs hybrid. Both work; the patterns in this wiki are technology-agnostic at the architectural level.

## What this architecture deliberately doesn't try to do

- It doesn't put MDM in the path of every transactional operation. Source systems remain the system of record for transactions; MDM is the system of record for identity.
- It doesn't replace operational system master data. ERP customer master remains. MDM is the consolidation, not the substitute.
- It doesn't try to be a data warehouse. Gold is the warehouse layer; MDM produces *into* gold, not as gold.
- It doesn't try to be a stewardship platform for non-MDM data. Other governed assets (financial close, regulatory reporting) have their own tooling.

The scope of MDM is *identity resolution and the master attributes that follow from it*. Keep it scoped; the program lives or dies on resisting scope creep.

## Sources

- Synthesis of patterns from this wiki.
- Architect's accumulated practice.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
