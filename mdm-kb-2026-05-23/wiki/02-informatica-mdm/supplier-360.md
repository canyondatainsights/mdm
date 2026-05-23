# Supplier 360

Supplier 360 is the supplier/vendor-domain MDM application. Same architectural lineage as Customer 360 — on-prem version on Multidomain MDM and a SaaS version on IDMC — but with a different data model emphasis and a different operational pattern.

## What's different from Customer 360

The data is structurally simpler in some ways and more complex in others.

**Simpler:**
- Lower volume. A large enterprise has 100K to 1M customers but only 1K-50K active suppliers. Match-merge volume is smaller.
- Better identifiers. Suppliers usually have tax IDs, DUNS numbers, registration numbers that are reliable. Fuzzy name matching matters less.
- Clearer ownership. Procurement owns supplier master, full stop. Customer master ownership is contested between sales, marketing, service, and finance; supplier is not.

**More complex:**
- Compliance overlay. Suppliers must be vetted (sanctions, anti-bribery, certifications), and the validation status has to be tracked over time. Certifications expire.
- Onboarding workflow. A new supplier goes through a multi-stage approval — initial request, due diligence, contract, ERP setup, payment terms. The MDM record progresses through states.
- Hierarchy importance. Parent-subsidiary relationships matter more for risk and spend rollups than for customer master in most cases.
- Self-service expectation. Suppliers expect to maintain their own profile through a portal.

## The supplier portal

Supplier 360 ships with (or integrates with) a Supplier Portal — an externally-facing web application where suppliers:

- Register and submit initial onboarding requests.
- Upload compliance documents (W-9, insurance certificates, ISO certifications, diversity certifications).
- Update their own contact info, banking details (with workflow approvals), and capability descriptions.
- View payment status, POs, and AP aging if integrated with the ERP.

The portal is a write path *into* MDM, mediated by stewardship workflows. A supplier-initiated bank-detail change does not automatically update the master — it goes into a pending state, triggers an approval task, and only updates after a procurement steward verifies and approves. This is fraud-prevention 101 and the most common mistake in supplier MDM is making the portal a direct-write path.

## Compliance lifecycle

Each supplier carries one or more *certifications* with effective dates, expiration dates, and document references. The MDM job for these:

- Track them as time-bounded records.
- Surface expiration warnings (60 / 30 / 7 days out) into the steward UI and into outbound notifications to the supplier.
- Block transactions if a critical certification expires (e.g., a supplier of regulated medical devices loses their FDA registration).
- Carry an audit trail of every certification change.

Where this typically integrates externally:

- **Dun & Bradstreet** for company verification, DUNS resolution, financial health.
- **Sanctions screening** (OFAC, EU Consolidated List, UN list) — through services like Refinitiv World-Check or Dow Jones Risk & Compliance. Run on every onboard and periodically thereafter.
- **Tax authority validation** — country-specific (IRS TIN matching in US, VAT VIES in EU).

These are typically called as external services during the onboarding workflow or scheduled batch jobs against the supplier base.

## Onboarding workflow (typical)

The Informatica ActiveVOS engine (on-prem) or the equivalent SaaS workflow handles this. Stages:

1. **Request initiated.** Either internal (procurement files a request to add a new supplier) or external (supplier registers on the portal).
2. **Initial data capture.** Legal name, tax ID, address, contacts.
3. **Duplicate check.** MDM searches for matching existing suppliers. If a match is found, the request is converted to "update existing" instead of "add new".
4. **Due diligence.** Sanctions screening, financial check, certification collection. Typically takes days to weeks depending on supplier criticality tier.
5. **Approval.** One or more approval levels depending on spend tier or risk classification.
6. **ERP setup.** Once approved, MDM publishes the supplier to ERP(s) as system of record for transactional data.
7. **Activated.** The supplier is usable for POs.

The MDM record progresses through state values (PENDING → IN_REVIEW → APPROVED → ACTIVE → INACTIVE) and each state may have different visibility and validation rules.

## Spend analytics integration

Supplier 360 is often paired with spend analytics. The flow:

```
ERP transactions (POs, invoices) → enriched with supplier master from MDM
                                ↓
                          Spend cube / warehouse
                                ↓
                    Spend dashboards, supplier scorecards
```

The MDM enrichment gives spend analytics:
- Consolidated supplier (not "Acme Inc" and "Acme, Incorporated" as two different vendors).
- Parent rollup (all spend with Acme group, including subsidiaries).
- Classification (preferred supplier? diverse? critical?).
- Certifications status as of transaction date (was the supplier active and certified at the time of the PO?).

## Common implementation patterns

- **Supplier 360 + Reference 360** is a natural pairing. Reference data — supplier classifications, commodity codes, payment terms — lives in Reference 360 and is referenced by Supplier 360.
- **Supplier 360 + Product 360** for catalog management. Suppliers upload product catalogs through the portal; Product 360 ingests, normalizes, and exposes to commerce/procurement.
- **Supplier 360 + Customer 360** when suppliers are also customers (joint-venture relationships, dealer networks). The party model in Customer 360 generalizes to either; some shops choose to run a single Party hub with role-based views.

## Sources

- docs.informatica.com — Supplier 360 10.4 Installation and Configuration Guide, *Informatica MDM Supplier 360 Application Overview*.
- Informatica product page: Supplier 360.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
