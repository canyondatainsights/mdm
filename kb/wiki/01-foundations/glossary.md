# Glossary

Working definitions for terms used across this wiki. Where Informatica uses a particular term that differs from generic industry usage, the Informatica meaning is given alongside the general one.

## A

**Address Verification (AV) / AddressDoctor.** Informatica's address-cleansing engine. Validates and standardizes postal addresses against country-specific reference data licensed from postal authorities. Versions 5 and 6 are current; 4 is legacy.

**Anomaly detection.** Statistical detection of values that deviate from historical norms. Snowflake provides this natively over DMF history; in Databricks it is typically implemented with Lakehouse Monitoring or custom logic.

## B

**Base Object.** The Informatica MDM table that holds the consolidated master record. Each domain (Customer, Supplier, Product) maps to one or more base objects. Survives the match-merge process. Equivalent in concept to the "golden record table" but Informatica-specific.

**BES (Business Entity Services).** Informatica's REST/SOAP API layer for accessing MDM data as logical business entities (Customer, Supplier) rather than raw tables. Modern successor to SIF for most use cases.

**Blocking key.** A computed field used to partition records into candidate-comparison groups during fuzzy matching. Without blocking, every record is compared to every other record (N²). With blocking, comparisons happen only within groups sharing the same blocking key. Common blocking keys: Soundex of last name, first 3 chars of postal code, normalized phone last-4.

**Bronze layer.** Medallion-architecture term for the raw-data layer. Append-only, schema-on-read, minimal transformation. The audit trail of what arrived from sources.

**BVT (Best Version of the Truth).** Informatica's term for the golden record. The consolidated record produced after match, merge, and survivorship rules apply.

## C

**CDC (Change Data Capture).** Mechanism for identifying which source records changed since the last load. Native in Snowflake (Streams), Databricks (Auto Loader + Delta CDF), and most modern ETL tools.

**CDQ (Cloud Data Quality).** Informatica's cloud-native data quality service in IDMC. The IDMC successor to on-prem IDQ Developer/Analyst tools.

**Cleanse Function.** In Informatica MDM Hub, a function applied during the staging-to-base-object load. Can be built-in, IDQ-published as a web service, or a custom user exit.

**Coexistence architecture.** MDM style where the hub and source systems are bidirectionally synchronized. The most common end-state for enterprise MDM programs.

**Consent.** In a privacy context, a freely given, specific, informed, and unambiguous indication of the data subject's agreement to processing of their personal data (GDPR Article 4). Must be capturable, withdrawable, and auditable.

**Consolidation indicator.** Informatica MDM internal field indicating where a record is in the match-merge lifecycle (NEW, READY_FOR_MATCH, ON_HOLD, CONSOLIDATED, etc.).

**Crosswalk.** A mapping between code sets in different source systems (e.g., country codes in CRM vs ERP). Lives in Reference 360 or in dedicated mapping tables.

## D

**DAMA-DMBOK.** Data Management Body of Knowledge. The reference text for the data management discipline. Useful for terminology and process frameworks; not a substitute for vendor-specific knowledge.

**Data Steward.** The human (role) responsible for the quality and meaning of a specific domain or set of data elements. Distinct from data owner (accountable, usually a business executive) and data custodian (operational, usually IT).

**Delta Lake.** Open table format underlying Databricks. Provides ACID transactions, schema enforcement, time travel, and the foundation for Delta Live Tables.

**DLT (Delta Live Tables).** Databricks' declarative ETL framework. You define tables and expectations; DLT manages execution, lineage, and quality monitoring.

**DMF (Data Metric Function).** Snowflake's native data-quality measurement primitive. SQL functions that produce metrics over tables; can be scheduled and have expectations attached.

**DPDP.** India's Digital Personal Data Protection Act, 2023.

**DSAR (Data Subject Access Request).** A request from a data subject under GDPR Article 15 (or equivalent) to receive a copy of their personal data and information about its processing.

**DSR (Data Subject Rights).** The broader category that includes DSAR plus rights to rectification, erasure, restriction, portability, objection, and human-in-the-loop for automated decisions.

## E

**Effective dating / bi-temporal.** Tracking both *system time* (when a record was loaded) and *valid time* (when the fact it represents was true). Informatica MDM supports this through history and timeline configuration.

**Expectation.** A data-quality rule applied to a record or table. In DLT, a Python decorator or SQL constraint. In Snowflake DMFs, a comparison between the DMF's output and a defined threshold.

## F

**Fuzzy match.** A match algorithm that allows variation (typos, transpositions, phonetic similarity) rather than requiring exact equality. Informatica uses the SSAName3 engine internally for name-tuned fuzzy matching.

## G

**GDPR.** General Data Protection Regulation, EU Regulation 2016/679. Applies to processing of personal data of individuals in the EU/EEA, regardless of where the processor is located.

**Golden record.** Generic term for the consolidated, authoritative version of an entity. Informatica's term is BVT (Best Version of the Truth).

**Gold layer.** Medallion-architecture term for the business-consumption layer. Aggregated, denormalized, optimized for analytics and downstream products.

## H

**Hierarchy Manager.** Informatica MDM component for managing relationships between entities (parent/subsidiary, household, organization charts). Distinct from base-object foreign keys; supports many-to-many and time-varying relationships.

**Hub Store.** Informatica MDM's underlying database schema set. Contains landing, staging, base objects, history, cross-reference, match candidates, etc.

## I

**IDMC (Intelligent Data Management Cloud).** Informatica's cloud platform. Houses MDM SaaS, Cloud Data Quality, Cloud Data Integration, and the rest of the cloud portfolio. Successor to the on-prem Informatica platform.

**IDQ (Informatica Data Quality).** On-prem data quality product (Developer Tool + Analyst Tool + Model Repository). Distinct from CDQ in IDMC.

## L

**Landing table.** Informatica MDM table that receives raw source data from upstream pipelines. One source-table-per-domain typically maps to one landing table. No transformations applied at landing.

**LGPD.** Brazil's Lei Geral de Proteção de Dados.

## M

**Match purpose.** In Informatica MDM, the type of match you're trying to identify (Person_Name, Organization_Name, Address_Part1, Wide_Contact, etc.). Each purpose has tuned algorithms and weights from Informatica's SSAName3 engine.

**Match rule.** A configured comparison between fields with a threshold and weights. Produces a match score; records above the auto-merge threshold are merged automatically, between thresholds are queued for steward review, below are not considered matches.

**Mapplet.** Reusable mapping fragment in IDQ. Encapsulates a transformation (cleanse, parse, standardize) that can be plugged into multiple parent mappings.

**Medallion architecture.** Bronze/silver/gold layered design pattern popularized by Databricks. See `wiki/04-pipelines-medallion/medallion-overview.md`.

## P

**PIM (Product Information Management).** Discipline and tooling for managing product master data, especially product attributes for commerce channels. Informatica Product 360 is a PIM.

**Profiling.** Statistical analysis of source data to characterize its structure, content, and quality (nulls, value distribution, pattern conformance, uniqueness). The starting point of every DQ project.

## Q

**Quarantine table.** Pattern for handling records that fail quality checks. Rather than dropping or failing, the record is routed to a quarantine table for inspection and reprocessing. Preferred over hard-drop for non-trivial data.

## R

**Reference data.** Code lists, classifications, and lookup values used to classify other data. Country codes, currency codes, internal classifications. Managed in Reference 360 or equivalent.

**Right to erasure (right to be forgotten).** GDPR Article 17. The data subject's right to have their personal data deleted under specific conditions. The hardest GDPR requirement to implement in MDM because the golden record is derived from multiple sources with their own retention obligations.

**ROWID_OBJECT.** Informatica MDM's internal primary key on base objects. A surrogate; do not expose externally.

## S

**Scorecard.** A grouped, visual display of profiling and DQ rule results, scored against thresholds. Standard reporting artifact in IDQ.

**SIF (Services Integration Framework).** Informatica MDM's older Java/SOAP API. Largely superseded by BES for new work.

**Silver layer.** Medallion-architecture term for the cleansed, validated, conformed layer. Where data quality is enforced before promotion to gold.

**SSAName3.** The fuzzy-matching engine inside Informatica MDM, originally developed by Search Software America. Specialized for person and organization name matching across languages.

**Staging table.** Informatica MDM table that sits between landing and base object. Cleansing and standardization happen during the landing-to-staging load. Each (source system × base object) pair has its own staging table.

**Stewardship.** The operational discipline of monitoring, correcting, and improving master data over time. Usually performed by a Data Steward role through a stewardship UI (Informatica Customer 360 provides one).

**Survivorship.** The process of choosing, cell-by-cell, which value wins when merging matching records. Driven by trust scores, source ranking, recency, and explicit rules.

## T

**Trust score.** A configurable per-source, per-column reliability score in Informatica MDM. Decays over time (configurable). Drives survivorship when multiple sources contribute to a record.

## U

**Unity Catalog.** Databricks' unified governance layer covering catalogs, schemas, tables, volumes, models, and fine-grained access control. Standard for new Databricks deployments.

## X

**XREF (Cross-Reference).** Informatica MDM's internal table linking each base-object record back to its contributing source records. The lineage trail of the golden record.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial glossary. |
