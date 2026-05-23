# Customer 360

Customer 360 is Informatica's flagship MDM application — the one most people mean when they say "Informatica MDM". It exists in two forms: the on-premises Customer 360 application running on Multidomain MDM 10.x, and Customer 360 SaaS on IDMC. Concepts overlap heavily; configuration UIs and operational characteristics diverge. This page covers what's specific to Customer 360 beyond what's in [`match-merge-survivorship.md`](match-merge-survivorship.md) and [`data-model-landing-staging-base-object.md`](data-model-landing-staging-base-object.md).

## The party model

Customer 360 ships with a *party model* — a generalized data model that handles both individuals and organizations as instances of *Party*. Key entities:

- **Party** — the abstract entity. Has a Party Type (Person or Organization).
- **Person** — biographical attributes (date of birth, gender, marital status). One-to-one with Party for individuals.
- **Organization** — legal entity attributes (tax ID, incorporation type, year founded). One-to-one with Party for orgs.
- **Address** — postal addresses, typed (billing, shipping, residence). One-to-many with Party.
- **Phone** — phone numbers, typed (mobile, home, work). One-to-many with Party.
- **Email** — email addresses, typed. One-to-many with Party.
- **Identifier** — external identifiers (passport number, tax ID, license number), typed. One-to-many with Party.
- **Relationship** — Party-to-Party links. Many-to-many, time-varying.

This is more general than strictly necessary for some implementations. If you only ever deal with consumer customers, the party-vs-person distinction adds overhead. If you handle B2B and B2C in the same hub, the party model pays for itself within a year.

## Hierarchy Manager

Hierarchy Manager handles parent/child and relationship structures that don't fit base-object foreign keys cleanly. Use cases:

- **Corporate hierarchies** — Acme Inc owns Acme UK Ltd, which owns Acme Manufacturing GmbH. Three Party records, two parent-of relationships.
- **Household linkage** — John, Jane, and their two kids share a household. Five Party records (four persons + one household), four member-of relationships.
- **Influencer / decision-maker networks** — B2B sales relationship graphs.

What makes Hierarchy Manager more than just a foreign-key column:

- **Many-to-many.** A subsidiary can be jointly owned. A person can be in two households (joint custody).
- **Time-bounded.** The relationship has effective-from and effective-to dates. Acme acquired Beta in 2018, divested in 2022.
- **Typed.** A "is-parent-of" relationship is distinct from "is-influencer-of" or "is-employee-of".
- **Configurable hierarchy profiles.** Different views of the same underlying relationships — legal structure vs operational structure vs reporting structure.

The downside: querying Hierarchy Manager from outside MDM requires understanding its data model, which is non-trivial. Most consuming systems get a flattened view materialized into a base-object foreign key or a separate relationship table.

## Business Entity Services (BES)

BES is the API layer over Customer 360 (and Multidomain MDM generally). It exposes business entities — Customer, Address, Phone — as logical objects through REST and SOAP. This replaces the older SIF (Services Integration Framework) Java API for most new work.

What you get:

- **Read** a Customer with embedded addresses/phones/emails in one call.
- **Search** with parameters (find all Customers with last name "Smith" in postal code 94025).
- **Create/Update** with side effects (writes go through the staging tables; match-merge runs on schedule).
- **Hierarchy navigation** — fetch a Party's relationships and the related Parties.

BES is the right integration pattern for:

- Real-time customer creation from a customer-facing app.
- Operational systems that need to look up a customer by attribute, not by MDM's internal ROWID.
- Workflow systems triggering on MDM events.

It is **not** the right pattern for bulk analytics consumption. For that, you want a materialized BVT view replicated to your warehouse, not BES API calls.

## Stewardship UI

The Customer 360 user interface is meant for two audiences: business users (browsing customer 360 views) and data stewards (resolving match-merge queues, fixing data quality exceptions). It's configurable per role.

Key stewardship workflows:

- **Match review queue.** Pairs of records in the "possible match" band, queued for human decision. The steward chooses merge, no-match, or escalate.
- **Manual merge.** Steward can initiate a merge of two records the system didn't auto-match.
- **Manual unmerge.** Steward can split a merged record back into its contributing components if the merge was wrong. Operationally painful but available.
- **Manual data correction.** Steward can override the BVT cell value. Every override should generate a question — *why didn't the rule produce this value?*
- **Exception management.** Records that failed validation or that have flags requiring attention.

The metric that tells you whether your stewardship operation is healthy: **review queue depth as a percentage of daily inflow.** If new pairs arrive faster than stewards process them, the queue grows and at some point becomes meaningless because everyone gives up. Target equilibrium or shrinkage.

## Customer 360 SaaS specifics

What's different in SaaS:

- **AI-driven matching** — Informatica's CLAIRE engine provides ML-based match suggestions in addition to (or sometimes instead of) configured rule-based matching. Effective when you have enough labeled history to train on; less helpful on day one.
- **Smart fields** — semi-structured fields that the platform parses, classifies, and standardizes automatically.
- **Quarterly releases** — features ship continuously. Pages in this wiki can drift; refresh when planning a specific feature use.
- **No Hub Console** — configuration is through Business 360 Console (web UI).
- **Microservices architecture** — different scaling characteristics; no JBoss/WebSphere to tune.

## Common integration patterns

- **Source → silver → MDM** — silver-layer table in Snowflake or Databricks is the canonical clean source; MDM consumes via CDI mapping into landing.
- **MDM → gold → consumers** — BVT views are replicated/materialized into the warehouse gold layer, where dashboards, reverse-ETL tools, and downstream analytics read from. Real-time consumers read via BES.
- **Customer self-service → MDM → CRM** — customer creates account on the website, BES creates the Party in MDM, MDM event triggers downstream Sync into Salesforce. The "create once, syndicate everywhere" pattern.

## Sources

- docs.informatica.com — Customer 360 10.5 Installation and Configuration Guide, Customer 360 SaaS user guides (Nov 2025 release).
- Informatica datasheet *Customer 360 Software as a Service*.
- Informatica training material referenced in initial KB build.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
