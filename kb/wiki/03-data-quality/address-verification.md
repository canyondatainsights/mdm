# Address Verification

Address verification is the most-licensed, most-cared-about piece of data quality in any customer or supplier MDM program. The reason is simple: addresses are messy, addresses matter (deliveries, geocoding, marketing, compliance), and bad addresses are visibly bad in a way that bad names aren't.

## What address verification does

Given an input address (which may be a single free-text blob or pre-parsed components), an AV engine returns:

- **Parsed components** — street number, street name, street type, unit, city, state/region, postal code, country.
- **Standardized form** — canonical capitalization and abbreviations according to the country's postal authority.
- **Verification status** — was this address found in the reference data? At what precision (delivery point, street, locality, postal area)?
- **Reference codes** — country-specific codes (USPS DPV in the US, AddressBase in the UK, etc.).
- **Geocoding** (optionally) — latitude/longitude at varying precision.
- **Suggestions** — if the address didn't verify exactly, what's the closest valid address(es)?

## Why DIY is a bad idea

Address verification looks deceptively simple. It's not. Reasons:

- **Reference data is licensed per country, from each country's postal authority.** USPS, Royal Mail, La Poste, Australia Post, Deutsche Post — each has its own data set, its own update cadence, its own license terms. Maintaining your own ingestion of these is a full-time job.
- **The data updates constantly.** New developments, renamed streets, new postcodes — monthly or quarterly updates per country. Stale reference data means addresses that exist in reality but don't verify in your system.
- **Country-specific rules are deep.** UK postcodes encode hierarchy (sector, district, area). Japanese addresses go large-to-small (prefecture → city → block → number). Russian addresses have multiple equally-valid romanizations. Hong Kong addresses can be in Chinese or English. Brazilian CEP postcodes have a specific format with regional encoding.
- **Multi-language and transliteration.** Addresses in countries with non-Latin scripts have official Latinized forms; you need to handle both.

Buy a licensed engine. Informatica AV is one option; alternatives include Loqate, Melissa, and Experian. The decision is usually cost and integration footprint, not capability.

## Informatica AV (AddressDoctor)

Informatica's offering, historically named AddressDoctor (the company Informatica acquired). Current versions are 5 and 6; version 4 is legacy and being phased out of new deployments.

Key facts:

- **Licensed per country per record volume per year.** Pricing depends on which countries you need and how many records you verify per year.
- **Reference data shipped separately.** You install the AV engine and download reference data files per country. The data files are large and updated quarterly.
- **License key required.** Has an expiration date. The session log will warn when expiration is near; check before it actually expires.
- **Integrations.** AV is callable from IDQ (Verifier asset), from MDM Hub cleanse adapter (on-prem), and from CDQ in IDMC. Same engine, different wrappers.
- **Modes.** Batch mode (process a file or table), interactive mode (verify one address at a time for a real-time UI), suggestion mode (return multiple candidate corrections for ambiguous input).

## Where AV goes in the pipeline

Three placements with different trade-offs:

**1. In the silver layer (Databricks/Snowflake)** — call AV from a pipeline notebook/UDF as part of silver materialization. Standardized addresses flow into MDM staging already verified.

- Pro: addresses are clean for any consumer, not just MDM.
- Pro: full control over the call pattern, retries, caching.
- Con: AV typically needs to run as a service (REST or local library); Databricks/Snowflake integration requires plumbing.
- Con: AV licensing usage is harder to track when distributed across pipelines.

**2. In MDM staging cleanse step** — AV is configured as a cleanse engine in MDM Hub. The cleanse function runs during landing-to-staging.

- Pro: tight integration, native to MDM.
- Pro: one licensed installation, easy to track usage.
- Con: only MDM benefits from verified addresses. Other consumers re-verify.
- Con: cleanse runtime is part of MDM batch job runtime; large volumes slow MDM batches.

**3. As a real-time service called by source applications** — your CRM verifies the address as the user types it.

- Pro: bad addresses are corrected at the moment of entry, before they ever enter your data estate.
- Pro: best customer experience.
- Con: each source application has to integrate; takes broader organizational alignment.

Mature programs do all three: real-time at entry where possible, silver-layer batch for bulk source loads, and MDM-staging as a final defense.

## What "verified" actually means

Several status codes are returned by AV; treat them differently.

- **Verified to delivery point.** The address exactly matches a deliverable address in the reference data. Highest confidence; use for matching, geocode, and deliverability.
- **Verified to street level.** The street exists; the number does not match a known delivery point but is plausible. Use with caution.
- **Verified to postal area / locality only.** The postcode/city/region exists; the street and number aren't verified. Don't use for delivery; can still be used for region-level analytics.
- **Ambiguous — multiple matches.** Several real addresses could correspond; AV returns candidates. Requires stewardship.
- **Failed to verify.** No match in the reference data. Either the address is wrong, the reference data is stale, or the address is in an unusual category (PO Box, military, rural route).

The MDM record should carry both the verified standardized address *and* the verification status. Downstream consumers (shipping, billing) can decide policy: refuse to ship to anything below delivery-point verification, or accept street-level for billing-only addresses.

## Multi-country addresses

The trap: most US-centric implementations assume an address has *one* postal code and a US-style structure. The world doesn't.

Practical advice:

- Store addresses in a normalized model with country-aware fields. The schema should accommodate a Japanese address (no "state" — has prefecture and ward), a UK address (postcode prefix and suffix), and an Indian address (PIN code, state, district, locality).
- Verify per country using country-specific reference data. AV handles this if you have the right countries licensed.
- Tag each address record with the country before verification so AV applies the right rules.

## Sources

- docs.informatica.com — *Configuring the Informatica Address Verification Cleanse Engine*, *Informatica Address Verification 5 Fields and Process Status Values*.
- Informatica Success Accelerator: *Informatica Data Quality - Address Validation Overview*.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
