# Data Governance — Overview

Governance is the boring half of MDM. Most programs fail not because the matching engine couldn't handle the data, but because no one was empowered to decide what "customer" means across three business units, or because the data steward role existed on the org chart but had no budget, time, or authority. The technology amplifies decisions; it doesn't make them.

This page lays out the operating model. The next pages cover the privacy-law layer that overlays it (GDPR, consent, right-to-erasure, other regimes).

## What governance actually is

Data governance is the framework of roles, responsibilities, decisions, and processes by which an organization manages its data as a strategic asset. In practice it answers four questions, repeatedly:

1. **Who owns this data?** (Accountability — usually a business executive.)
2. **Who maintains its quality?** (Stewardship — usually a domain SME.)
3. **Who can use it, for what?** (Access and purpose limitation.)
4. **What happens when it changes, breaks, or is contested?** (Process — workflows, escalation, dispute resolution.)

If you can't answer these for a given data domain, you don't have governance over it. You have a database.

## Roles

The roles below are common across DAMA-DMBOK literature and most enterprise programs. Names vary; functions are stable.

**Data owner.** Accountable for a data domain. Usually a business executive — VP Customer, Head of Procurement, Chief Marketing Officer. Approves policy. Makes the call when stewards can't agree. Funds the steward function. *Not* hands-on with data day-to-day.

**Data steward.** Operationally responsible for the domain. Defines standards, monitors quality, resolves exceptions, triages match-merge queues, owns the rules. Usually a domain expert from the business with a partial allocation (often 30-60% of time) rather than a full-time role. The steward function lives or dies on whether this allocation is real and protected.

**Data custodian.** Operationally responsible for the *infrastructure* holding the data. Usually IT — the DBA, the platform engineer, the MDM developer. Implements what the steward and owner decide. Does not unilaterally make business policy decisions.

**Data architect.** Designs the data models, integration patterns, and architectural choices. Bridges business intent and technical implementation. Authors ADRs.

**Data consumer.** Anyone who reads or uses the data — analysts, application owners, downstream systems. Provides feedback when data doesn't meet their needs.

**Data protection officer (DPO).** Required under GDPR for organizations meeting certain thresholds. Independent role; can be in-house or external. Advises on privacy compliance, monitors processing, liaises with supervisory authorities. Not the same as the data owner — DPO's mandate is regulatory compliance, owner's is business value.

**Chief Data Officer (CDO).** Executive owner of the entire data function. Funds the program, sets enterprise-wide standards, chairs the data council. Most enterprises now have one; many have had three in five years because the role is hard and political.

## The data council

Most governance programs run a council (data governance committee, data steering committee — names vary) where stewards and owners meet to:

- Approve cross-domain decisions (one domain affects another).
- Resolve disputes between owners.
- Approve new data sources entering MDM.
- Review program metrics — quality scorecards, stewardship queue depth, incident counts.
- Approve policy changes.

The council should meet often enough to be useful (monthly is typical) and short enough that people will actually attend (60-90 minutes max).

## RACI for MDM operations

For each operational activity, who is Responsible, Accountable, Consulted, Informed:

| Activity | Responsible | Accountable | Consulted | Informed |
|---|---|---|---|---|
| Define what "customer" means | Customer domain steward | Customer data owner | Other domain stewards, legal | All consumers |
| Approve new source onboarding | Customer domain steward + architect | Customer data owner | Source-system owner, DPO | Data council |
| Tune match rules | MDM developer + steward | Customer data owner | Architect | Data council |
| Resolve match queue items | Steward | Steward | — | — |
| Approve manual BVT override | Steward | Customer data owner | — | Audit log |
| Handle GDPR erasure request | DPO + steward | DPO | Customer data owner, legal | Data subject |
| Add new DQ rule | Steward + MDM developer | Customer data owner | Architect | Data council |
| Major schema change | Architect | Data owner | Stewards, MDM developer | All consumers |
| Approve cross-domain data sharing | Both owners + DPO | Both owners | Legal | Data council |

Every program has variants of this. The point is to write it down. If the RACI is implicit, decisions get bottlenecked or made wrong.

## DAMA-DMBOK alignment

The DAMA-DMBOK2 (Data Management Body of Knowledge) is the field's reference text. It defines 11 knowledge areas of data management; MDM and Data Governance are two of them, but they touch all the others.

The knowledge areas relevant to an MDM program:

- **Data Governance** — the foundation (this page).
- **Data Architecture** — how data is structured and integrated (covered across this wiki).
- **Data Modeling and Design** — entity/relationship modeling (touched on in MDM domain pages).
- **Data Storage and Operations** — the platform infrastructure (Snowflake, Databricks, Informatica Hub).
- **Data Security** — access control, encryption, masking (covered in the privacy pages).
- **Data Integration and Interoperability** — pipelines, ETL, CDC (medallion section).
- **Reference and Master Data** — the heart of MDM.
- **Data Warehousing and Business Intelligence** — gold layer consumption.
- **Metadata** — lineage, catalogs (Unity Catalog, Atlan, Collibra, etc.).
- **Data Quality** — DQ section.

You don't need DAMA-DMBOK to do MDM well, but it gives you shared vocabulary with other data professionals and a checklist of concerns you might otherwise miss.

## Where governance and the technology meet

The technology decisions that have governance implications:

- **Trust scores and source ranking.** Configured in MDM; reflect a business decision about which source is authoritative. Steward / owner decides; developer implements.
- **Match thresholds.** Reflect tolerance for false positives vs false negatives. Owner decides; developer implements.
- **Survivorship rules.** Reflect "which value wins" — a business policy decision.
- **Validation rules.** Reflect what quality means. Steward decides; developer implements.
- **Access control on PII columns.** Reflects legal and policy positions on who can see what. DPO and owner decide; custodian implements.
- **Retention policies.** Reflect legal and business requirements. DPO, legal, and owner decide together.

Each of these is a place where the wrong configuration is a *governance* failure, not a technical one.

## Metrics worth tracking

- **Stewardship queue depth.** Pending match-review items, exceptions, overrides. Should trend flat or shrinking.
- **Time to resolve queue items.** Hours / days from arrival to resolution. Watch trends.
- **DQ scorecard pass rates.** Per domain, trended weekly/monthly.
- **Records under stewardship.** Of total records, how many have had manual intervention recently? A signal of rule maturity.
- **Source quality scorecard.** Per source — incoming quality. Lets you have evidence-based conversations with source-system owners.
- **DSR turnaround time.** GDPR-mandated metrics: time from request to fulfillment for access, rectification, erasure.
- **Incident count.** Data quality incidents that hit production reports / customers / regulators.

If these don't get measured, the program optimizes for things you can see (cost, throughput) at the expense of things you can't (quality, trust).

## Sources

- DAMA-DMBOK2 — knowledge areas.
- DGPC (Data Governance Professionals Community) reference material.
- Architect's accumulated practice.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
