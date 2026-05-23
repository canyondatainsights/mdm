# Informatica Data Quality — Overview

Two products bear this name, with different deployment models:

- **IDQ (Informatica Data Quality)** — the on-premises product. Developer Tool + Analyst Tool + Model Repository Service. Runs alongside PowerCenter / Data Engineering / on-prem MDM.
- **Cloud Data Quality (CDQ)** — the IDMC service. Cloud-native, browser-based, integrated with the rest of IDMC (Cloud Data Integration, MDM SaaS, Cloud Application Integration).

The features are largely equivalent and the rule definitions are portable in concept (the actual UI and asset formats differ). For new programs, default to CDQ unless you have an on-prem MDM stack that benefits from local IDQ proximity.

## What DQ does in this context

The job of data quality, in service of MDM, is to ensure that data arrives at the MDM hub in a state where match rules can do their job. That means:

- **Profiling** the source data to understand what you have.
- **Cleansing** — removing noise, fixing case, trimming, deduplicating exact dups.
- **Standardizing** — converting variations to canonical forms (St → Street, ph# → 1234567890).
- **Validating** — applying rules that check correctness against patterns, ranges, reference data.
- **Parsing** — decomposing compound fields (full name → first/middle/last; full address → street/city/state/zip).
- **Enriching** — adding data from authoritative sources (address verification, geocoding, demographic appends).
- **Matching** — deduplication within a single source before MDM sees it. (MDM does its own cross-source matching; pre-deduping the source first reduces noise.)
- **Reporting** — scorecards that surface DQ trends over time.

## The six DQ dimensions

The DAMA-aligned dimensions you'll see repeatedly:

1. **Completeness** — are required values present? Is the field populated?
2. **Conformity** — does the value conform to a defined format or pattern? (Email looks like an email; postal code matches country's format.)
3. **Consistency** — do values agree across related fields and records? (State and postal code match; transaction amounts sum correctly.)
4. **Accuracy** — does the value reflect real-world truth? (The street address exists; the phone is reachable.)
5. **Uniqueness** — is the value or record unique where it should be? (One email per customer; one tax ID per supplier.)
6. **Timeliness** — is the data fresh enough for its intended use?

Profiling can measure five of these mechanically. *Accuracy* almost always requires reference data — the address exists according to USPS, the company exists according to Dun & Bradstreet, the tax ID validates against the tax authority. This is why address verification, business directory enrichment, and identity verification services exist.

## Where DQ runs in the pipeline

Three placements, each with trade-offs:

**Upstream of MDM, in the silver layer.** Cleansing and standardization happen during silver materialization in Databricks/Snowflake. MDM consumes already-clean data.
- Pro: clean data is reusable beyond MDM. Other consumers benefit.
- Pro: cleansing logic in version-controlled pipeline code, easy to test.
- Con: silver-layer team and MDM team must coordinate on what "clean" means.
- Con: address verification typically requires a licensed engine (Informatica AV); spinning that up outside MDM duplicates licensing.

**Inside MDM, in the staging cleanse step.** Landing data is raw; cleansing happens during landing-to-staging via cleanse functions (built-in or IDQ-published web services).
- Pro: MDM owns its data quality end-to-end.
- Pro: single licensed instance of AV.
- Con: cleansing logic is harder to test in isolation.
- Con: other consumers don't benefit from the cleansing.

**Both — split by what you're cleansing.** General cleansing (case, trim, format) in silver. MDM-specific cleansing (address verification, name parsing tuned for matching) in staging.
- Pro: each layer does what it's best at.
- Con: more pieces to maintain; people sometimes forget which layer cleans what.

The "both" pattern is what most mature shops settle on. Document the boundary in an ADR.

## IDQ asset types

When you work in IDQ (or CDQ), the key asset types are:

- **Profile** — a definition of "run these statistics over this data source". Output: profiling results showing nulls, value distributions, patterns, uniqueness.
- **Rule (rule specification)** — a business-level rule expressed in IDQ's rule language. "Email must contain exactly one @ and a valid TLD."
- **Mapplet** — a reusable transformation. Encapsulates parsing, cleansing, or standardization logic. Can be plugged into multiple mappings.
- **Mapping** — an end-to-end pipeline from source to target with transformations in between.
- **Scorecard** — a grouped view of rule results, scored against thresholds, with trend over time.
- **Reference table** — a managed lookup (valid country codes, common name variants, allowed values). Stored in the Model Repository.
- **Cleanse function library** — for MDM integration, IDQ mappings can be published as web services consumed by MDM cleanse steps.

## Versioning and reuse

The Model Repository stores everything. Multiple developers collaborate via check-in/check-out. For CDQ, asset management is through IDMC's standard mechanisms.

What to design for reusability:

- **Rules** — write one "valid email" rule, use it everywhere.
- **Mapplets** — write one "standardize US phone" mapplet, reuse across sources.
- **Reference tables** — maintain one master list of valid values per domain; consume from multiple rules.

What to keep source-specific:

- **Mappings** — each source has its own quirks; the end-to-end mapping is per-source.
- **Profile definitions** — different sources need different statistics.

## Sources

- docs.informatica.com — *Informatica Data Quality and Profiling* (Developer Tool Guide).
- Informatica product page: Data Quality and Observability.
- Informatica Success Accelerator: *Data Quality Using Cleanse and Standardization Techniques*.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
