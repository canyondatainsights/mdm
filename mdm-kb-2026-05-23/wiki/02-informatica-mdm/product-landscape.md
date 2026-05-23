# Informatica MDM Product Landscape

The first thing to get straight: **Informatica sells several products with "MDM" in the name, and they are not the same architecture.** A pattern that works in Multidomain MDM 10.5 will not necessarily work the same way in MDM SaaS, and vice versa. Asking "how do I do X in Informatica MDM" without specifying which product wastes everyone's time.

This page draws the boundaries.

## The two architectural lineages

**Multidomain MDM (the 10.x line).** The classic on-premises product. Java application server (typically WebSphere or JBoss/WildFly historically; current deployments often containerized), Oracle/DB2/SQL Server-backed Hub Store, Hub Console (Java thick client) for configuration, Customer 360 / Supplier 360 web applications layered on top. Versions 10.3, 10.4, 10.5 with hotfixes are what you find in the wild. The data model (landing → staging → base object → BVT) is canonical and well-documented.

**MDM SaaS (the IDMC line).** Cloud-native, multi-tenant, microservices-based, delivered through Informatica's Intelligent Data Management Cloud. Configuration through Business 360 Console. Different data model abstractions (business entities, smart fields, AI-driven matching). The on-prem concepts are still recognizable but the implementation, configuration UI, and operational characteristics are different. Releases ship quarterly or faster.

Informatica is gradually consolidating around the SaaS line for new business; on-prem Multidomain MDM is supported but most new programs start on SaaS. **If you are starting a new program in 2026, the default assumption should be MDM SaaS unless there is a specific reason (data residency, regulatory) that forces on-prem.**

## The 360 applications

These are domain-specific applications layered on top of one of the two MDM cores. They provide pre-built data models, business processes, dashboards, and stewardship UIs for a specific entity type.

### Customer 360

Manages customer/party data. Available in both flavors:

- **Customer 360 (on-prem)** — runs on Multidomain MDM 10.x. Configured through Provisioning Tool. Hierarchy Manager for parent/subsidiary relationships. Integrates with Informatica ActiveVOS for workflow.
- **Customer 360 SaaS (C360 SaaS)** — IDMC application. AI-driven matching, configurable UI, microservices architecture. Quarterly release cadence. As of the November 2025 release, AI-driven matching and smart fields are standard.

Use cases: customer onboarding, KYC/AML support, single-customer-view for analytics, marketing personalization, customer service 360 view, household linkage.

### Supplier 360 (S360)

Manages supplier/vendor data. Similar split between on-prem and SaaS. Adds:

- **Supplier portal** — externally-facing application where suppliers self-register, upload compliance documents, and maintain their own profile.
- **Compliance workflows** — certifications, sanctions, expiration tracking.
- **Spend visibility integration** — usually paired with downstream procurement analytics.

The matching problem for suppliers is generally easier than for customer (fewer records, more structured names, tax IDs are reliable identifiers) but the workflow complexity is higher.

### Product 360 (P360)

Distinct from Customer/Supplier 360. P360 is fundamentally a **PIM (Product Information Management) system**, not a pure MDM. It manages:

- Product master attributes, taxonomies, hierarchies.
- Channel-specific data variants (web, print, marketplace, B2B portal).
- Supplier catalogs and data syndication.
- Media (images, documents) and rich content.
- AI-powered classification, attribute extraction, and enrichment (recent feature additions).

If your "product MDM" problem is mostly attribute management and channel syndication, P360 is the right tool. If it's mostly matching/deduplication of products across multiple ERPs, the regular MDM cores (Multidomain MDM or MDM SaaS) may fit better.

### Reference 360

Manages reference data: code lists, classifications, crosswalks, hierarchies. Versioned, effective-dated, audit-trailed. Available as an IDMC service.

The mistake most programs make is treating reference data as an afterthought ("it's just a lookup table"). Country codes diverge across systems. Currency codes have effective dates (CHF before/after 2015 redenomination scares, EUR adoption dates by country). Internal classifications get re-orged annually. Without Reference 360 (or equivalent discipline), every consuming system implements its own broken crosswalk and the result is reconciliation hell.

## What about the "MDM Cloud" product naming?

Informatica's marketing has used variations including *MDM Cloud*, *Cloud MDM*, *Cloud Customer 360*, and *Customer 360 SaaS*. As of the IDMC consolidation, **the canonical naming is the SaaS variants of each 360 application running on the IDMC platform.** Older material that references "MDM Cloud" generally means what we now call MDM SaaS.

## Choosing a starting point

| If you have... | Start here |
|---|---|
| Existing 10.x MDM deployment, working today | Stay on Multidomain MDM. Upgrade path to SaaS is a migration, not an upgrade. |
| Greenfield customer MDM program, no sovereignty constraint | Customer 360 SaaS |
| Greenfield supplier program | Supplier 360 SaaS |
| Product data with channel syndication needs | Product 360 |
| Reference data chaos | Reference 360 (regardless of which MDM core you use) |
| Data residency restriction outside Informatica's cloud regions | Multidomain MDM on-prem or in your own cloud |

## What this wiki documents

This wiki tries to be honest about which variant a given page applies to. Where on-prem and SaaS diverge meaningfully, both are covered. Where they're conceptually similar but configurationally different, the page describes the concept and notes the divergence.

Key pages where the on-prem/SaaS distinction matters most:

- [`data-model-landing-staging-base-object.md`](data-model-landing-staging-base-object.md) — applies cleanly to on-prem; SaaS abstracts the layers.
- [`match-merge-survivorship.md`](match-merge-survivorship.md) — concepts are universal; configuration UIs differ.

## Sources

- docs.informatica.com — Customer 360 10.5 documentation, Supplier 360 10.4 documentation, Product 360 10.5 documentation, MDM SaaS user guides (Nov 2025 release).
- Informatica datasheet *Customer 360 Software as a Service* (informatica.com).
- Informatica datasheet *Intelligent MDM and 360 Applications*.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. Reflects product landscape as of November 2025 release of MDM SaaS and on-prem 10.5 Hotfix 5. |
