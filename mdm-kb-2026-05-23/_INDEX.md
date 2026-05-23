# Wiki Index

Each entry links to a wiki page and gives a one-line description of what it covers. Update this file whenever a page is added or a topic split.

## 01 — Foundations

- [`01-foundations/mdm-concepts.md`](wiki/01-foundations/mdm-concepts.md) — What MDM is, what it isn't, the four common architecture styles (registry, consolidation, coexistence, centralized), and where Informatica MDM sits.
- [`01-foundations/glossary.md`](wiki/01-foundations/glossary.md) — Working definitions for terms we use across the wiki. The single place to disambiguate "trust" vs "survivorship" vs "BVT" etc.

## 02 — Informatica MDM (product family)

- [`02-informatica-mdm/product-landscape.md`](wiki/02-informatica-mdm/product-landscape.md) — Multidomain MDM (on-prem/10.x) vs MDM SaaS / Customer 360 SaaS / Supplier 360 SaaS / Reference 360 / Product 360. They are not the same architecture; this page draws the boundary.
- [`02-informatica-mdm/data-model-landing-staging-base-object.md`](wiki/02-informatica-mdm/data-model-landing-staging-base-object.md) — The classic Hub data flow: landing → staging → base object → BVT. Where cleanse functions plug in.
- [`02-informatica-mdm/customer-360.md`](wiki/02-informatica-mdm/customer-360.md) — Customer 360 specifics: party model, relationships, hierarchy manager, business entity services (BES).
- [`02-informatica-mdm/supplier-360.md`](wiki/02-informatica-mdm/supplier-360.md) — Supplier 360 specifics: supplier portal, compliance, onboarding workflows.
- [`02-informatica-mdm/product-360.md`](wiki/02-informatica-mdm/product-360.md) — Product 360 specifics: PIM-style content management, supplier catalogs, channel syndication.
- [`02-informatica-mdm/reference-360.md`](wiki/02-informatica-mdm/reference-360.md) — Reference data: code lists, crosswalks, hierarchies, versioning.
- [`02-informatica-mdm/match-merge-survivorship.md`](wiki/02-informatica-mdm/match-merge-survivorship.md) — Match rules (exact, fuzzy, segment), match purposes, thresholds, trust scores, survivorship precedence, decay. The page most people will ask about.

## 03 — Data Quality (Informatica IDQ / CDQ)

- [`03-data-quality/idq-overview.md`](wiki/03-data-quality/idq-overview.md) — IDQ on-prem vs Cloud Data Quality (CDQ) in IDMC. Profiling, scorecards, rules, mapplets.
- [`03-data-quality/profiling-and-scorecards.md`](wiki/03-data-quality/profiling-and-scorecards.md) — How to profile, what to measure (the six DQ dimensions), how to translate findings into rules.
- [`03-data-quality/cleansing-standardization.md`](wiki/03-data-quality/cleansing-standardization.md) — Standardization patterns (names, phones, dates), reference tables, Verifier, parsing.
- [`03-data-quality/address-verification.md`](wiki/03-data-quality/address-verification.md) — Address Doctor / Address Verification 5/6, reference data files, where to do address validation in the pipeline.
- [`03-data-quality/dq-rule-design.md`](wiki/03-data-quality/dq-rule-design.md) — How to write DQ rules that are reusable, testable, and don't rot. Naming, parameterization, exception handling.

## 04 — Pipelines & Medallion Architecture

- [`04-pipelines-medallion/medallion-overview.md`](wiki/04-pipelines-medallion/medallion-overview.md) — Bronze / Silver / Gold: what belongs in each layer, what doesn't, common anti-patterns.
- [`04-pipelines-medallion/staging-for-mdm.md`](wiki/04-pipelines-medallion/staging-for-mdm.md) — Mapping medallion layers to Informatica's landing/staging/base-object model. Where the boundary lives and how to avoid duplicating cleansing in two places.
- [`04-pipelines-medallion/pipeline-design-patterns.md`](wiki/04-pipelines-medallion/pipeline-design-patterns.md) — Idempotency, CDC, late-arriving data, quarantine tables, replayability.

## 05 — Snowflake

- [`05-snowflake/snowflake-for-mdm-staging.md`](wiki/05-snowflake/snowflake-for-mdm-staging.md) — Stages (internal/external), Snowpipe, tasks, streams. How to stand up a bronze/silver pipeline that feeds Informatica MDM.
- [`05-snowflake/data-metric-functions.md`](wiki/05-snowflake/data-metric-functions.md) — System DMFs, custom DMFs, scheduling, expectations, monitoring views. SQL patterns included.
- [`05-snowflake/sql-cleansing-patterns.md`](wiki/05-snowflake/sql-cleansing-patterns.md) — Practical SQL for name standardization, deduplication, blocking-key generation, soundex/metaphone in Snowflake.

## 06 — Databricks

- [`06-databricks/databricks-for-mdm-staging.md`](wiki/06-databricks/databricks-for-mdm-staging.md) — Auto Loader, Unity Catalog, Delta Lake fundamentals for the MDM use case.
- [`06-databricks/dlt-expectations.md`](wiki/06-databricks/dlt-expectations.md) — Delta Live Tables expectations: expect, expect_or_drop, expect_or_fail. When each is right.
- [`06-databricks/pyspark-cleansing-patterns.md`](wiki/06-databricks/pyspark-cleansing-patterns.md) — PySpark for name/address normalization, fuzzy-match blocking, deduplication at scale.

## 07 — Governance & Consent

- [`07-governance-consent/data-governance-overview.md`](wiki/07-governance-consent/data-governance-overview.md) — Stewardship, ownership, DAMA-DMBOK alignment, RACI for MDM programs.
- [`07-governance-consent/gdpr-fundamentals.md`](wiki/07-governance-consent/gdpr-fundamentals.md) — The seven principles, lawful bases, data subject rights, controller/processor.
- [`07-governance-consent/consent-management.md`](wiki/07-governance-consent/consent-management.md) — Capturing consent, withdrawal, granularity by purpose, consent records as auditable data.
- [`07-governance-consent/right-to-erasure-in-mdm.md`](wiki/07-governance-consent/right-to-erasure-in-mdm.md) — The hardest GDPR problem for MDM: erasing a person from the golden record without breaking lineage, downstream systems, or legal hold. Concrete patterns.
- [`07-governance-consent/ccpa-and-other-laws.md`](wiki/07-governance-consent/ccpa-and-other-laws.md) — CCPA/CPRA, UK GDPR, LGPD, India DPDP. The deltas worth knowing.

## 08 — Patterns & Playbooks

- [`08-patterns-playbooks/end-to-end-reference-architecture.md`](wiki/08-patterns-playbooks/end-to-end-reference-architecture.md) — A worked reference: sources → lake/warehouse (bronze/silver) → IDQ cleanse → Informatica MDM (staging/base-object) → gold consumption → downstream syndication.
- [`08-patterns-playbooks/source-onboarding-checklist.md`](wiki/08-patterns-playbooks/source-onboarding-checklist.md) — What to ask, what to profile, what to build before letting a new source into MDM.
- [`08-patterns-playbooks/match-rule-tuning-playbook.md`](wiki/08-patterns-playbooks/match-rule-tuning-playbook.md) — The iterative loop for tuning match rules. Test sets, false-positive/false-negative analysis, threshold sweeps.

## 09 — Architecture Decision Records (ADRs)

- [`09-decisions-adrs/_template.md`](wiki/09-decisions-adrs/_template.md) — Template for new ADRs.
- ADRs are numbered (`ADR-001-*.md`) and added as we make decisions worth recording.

---

## Revision log

| Date | Change | Author |
|---|---|---|
| 2026-05-23 | Initial index created (v1.0). All pages stubbed and written from current public documentation as of May 2026. | Architect |
