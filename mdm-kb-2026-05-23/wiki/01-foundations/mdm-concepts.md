# MDM Concepts

Master Data Management is the discipline of producing and maintaining a single, authoritative version of the entities your business cares about — typically customers, suppliers, products, employees, locations, and the reference data that classifies them. The phrase "single version of the truth" is overused and slightly misleading. What MDM actually produces is a *trusted, reconciled view* assembled from multiple operational sources, with rules that govern conflict resolution, history, and access.

If you walk away with one thing from this page: **MDM is not a database. It is a process for resolving conflicting facts about the same real-world entity across systems, and the technology is the scaffolding for that process.**

## The four architectural styles

You will see these four patterns in any MDM textbook and in every vendor's positioning slides. Knowing which one you're actually building is the most important early decision.

**Registry.** MDM stores only the cross-reference (which records in which sources refer to the same entity) plus a thin minimal record. Source systems remain authoritative for the data itself. Reads against MDM return a pointer; full data is assembled by federation. Lowest risk, lowest value. Good for read-mostly use cases like analytics and reporting where you mostly need to know that records X, Y, Z are the same customer.

**Consolidation.** MDM consolidates source data into a hub used primarily for analytics and reporting. Writes still go to source systems; the hub is downstream. This is the most common starting point because it doesn't require source-system change.

**Coexistence.** MDM holds the golden record and is bidirectionally synchronized with source systems. Source systems remain operational, but they reconcile to MDM on a schedule or via events. This is where most enterprise programs end up landing — it gives MDM authority without forcing a single front-end for all data entry.

**Centralized (transactional / system of entry).** MDM is the system of entry. All creates and updates happen in MDM and are pushed to consumers. Highest value, highest cost, highest organizational change. Usually only feasible for green-field domains or after years of program maturity.

Informatica's products support all four, but their default posture (especially Customer 360 SaaS with its onboarding workflows and stewardship UI) is coexistence leaning toward centralized for the high-value domains.

## What MDM is not

- **Not a data warehouse.** A warehouse stores facts and dimensions for analytics. MDM stores entities and their relationships for operational use. The warehouse may consume from MDM, but they answer different questions.
- **Not a CRM.** CRM stores customer *interactions*. MDM stores the customer *identity*. CRM is a source to MDM, not a substitute.
- **Not a data quality tool.** DQ profiles, cleanses, and validates. MDM matches, merges, and survives. You need both. Confusing them is the single most common mistake on first projects.
- **Not a one-time project.** The hardest part of MDM isn't the initial load — it's the operating model that keeps the golden record trustworthy after every source system adds a field or every business unit acquires a company.

## The four common domains

- **Customer** (and party, which generalizes to person + organization). The most-implemented domain. Hardest matching problems (name/address variation, household linkage, B2B vs B2C nuances).
- **Supplier / Vendor.** Smaller volume than customer but with heavier compliance overlay (KYC, sanctions, tax, certifications). Hierarchies (parent/subsidiary/division) matter more than for customer in many cases.
- **Product.** Often handled separately as PIM (Product Information Management) — Informatica Product 360 is a PIM. The matching problem is different: products are usually identified by stable SKUs, but enrichment, taxonomy, and channel-specific attribute management dominate.
- **Reference / code list.** Often dismissed as trivial, then becomes the bottleneck. Country codes, currency codes, units of measure, internal classifications, chart-of-accounts segments. Reference data needs versioning, effective-dating, and crosswalks between source-system code sets. Informatica Reference 360 exists specifically for this.

## The golden record

A *golden record* (Informatica also calls this the *Best Version of the Truth* or BVT) is the consolidated record produced by the match-and-merge process. Each cell in the golden record may have come from a different source, chosen by survivorship rules. The golden record is not "the right record" — it is the record the rules produced. If the rules are wrong, the golden record is wrong, and "fixing the data" means fixing the rules, not editing the record.

This is a discipline issue, not a technical one. Data stewards will want to type into the golden record. They can — Informatica supports manual override — but every manual edit is a signal that a rule is missing or wrong. Track them.

## The fundamental tension

MDM has one fundamental tension that everything else descends from: **business systems are optimized for transactions; MDM is optimized for identity.** A CRM cares whether a sales rep can log a call against an account in three clicks. MDM cares whether that account is the same legal entity as the one in the ERP and the marketing automation platform. These two goals pull in opposite directions. Resolving the tension is half political (who owns the data?) and half technical (what does the match rule say?). Programs that under-invest in the political half fail.

## Sources

- Informatica MDM Customer 360 documentation (docs.informatica.com).
- DAMA-DMBOK2 (the field's reference text for terminology and process).
- Practitioner experience captured during initial KB build.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
