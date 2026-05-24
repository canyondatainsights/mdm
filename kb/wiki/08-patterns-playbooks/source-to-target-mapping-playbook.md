---
vendor: informatica
domain: customer
scope: vendor-specific
---

# Source-to-Target Mapping Playbook

A **source-to-target (S2T) mapping** is the specification that says, field by field, how data
from a source system lands in the target model. It is the contract between source onboarding
and the MDM hub — the thing a developer builds the load to, and a steward reviews for
correctness. This playbook defines the standard shape and how to produce a good one for
Informatica Customer 360 (use the [`customer-360-data-model-reference.md`](../02-informatica-mdm/customer-360-data-model-reference.md)
for the target side).

## Standard columns

Always use this column set, in order:

| Column | Meaning |
|---|---|
| Source Object | The source table/object (e.g. CRM `Contact`, `Account`) |
| Source Field | The source attribute |
| Target Business Entity | C360 entity — **Person** or **Organization** |
| Target Field Group | The C360 field group (Postal Address, Phone Communication, …) |
| Target Field | The exact C360 field |
| Data Type | Target data type (String, Date, Boolean, Lookup, …) |
| Transformation | The rule to get from source to target |
| Notes | Lineage, exceptions, match-key/survivorship flags, provenance |

## Transformation vocabulary

`pass-through` · `trim` · `upper` / `lower` / `proper-case` · `concat` · `split` ·
`parse` (e.g. full name → parts) · `lookup` (crosswalk a source code to a reference code) ·
`coalesce` / `null-to-default` · `format` (dates, phone) · `derive` (computed) ·
`standardize` (address/phone via DQ) · `dedupe-key` (used in matching).

## Conventions
- **Match keys** — flag fields that feed match rules (name, email, identifier, postal code) in
  Notes so survivorship and matching are considered.
- **Lookups** — when a source code must map to a C360 reference code set, note the crosswalk
  (see [`reference-360.md`](../02-informatica-mdm/reference-360.md)).
- **Typed multi-value groups** — Address/Phone/Email rows should set the type and the
  `Default Indicator`.
- **Provenance** — if a target field comes from the C360 model in general (not a cited KB
  page), say so in Notes (e.g. "general C360 model"); cite the reference page where it applies.
- **PII** — flag restricted fields (identifiers, DOB) in Notes for masking/governance.

## Worked example — generic CRM → Customer 360 (Person)

| Source Object | Source Field | Target Business Entity | Target Field Group | Target Field | Data Type | Transformation | Notes |
|---|---|---|---|---|---|---|---|
| Contact | contact_id | Person | General | Source Primary Key | String | pass-through | Source natural key; dedupe-key |
| Contact | first_name | Person | General | First Name | String | trim | Match key |
| Contact | last_name | Person | General | Last Name | String | trim | Match key |
| Contact | birthdate | Person | General | Date of Birth | Date | format(ISO-8601) | PII |
| Contact | email | Person | Electronic Address | Email Address | String | lower; standardize | Match key; set Default Indicator |
| Contact | mobile_phone | Person | Phone Communication | Phone Number | String | parse(country code); standardize | Phone Type = mobile |
| Contact | mailing_street | Person | Postal Address | Address Line 1 | String | trim | Address Type = mailing |
| Contact | mailing_city | Person | Postal Address | City | String | trim | |
| Contact | mailing_state | Person | Postal Address | State | Lookup | lookup(State) | Crosswalk source code → State ref |
| Contact | mailing_country | Person | Postal Address | Country | Lookup | lookup(Country) | |
| Contact | mailing_zip | Person | Postal Address | Postal Code | String | trim | |
| Contact | email_opt_in | Person | Communication Channel Preferences | Opt-In / Opt-Out | Boolean | derive | Channel = email (consent extension) |

> Download any mapping the assistant produces with the **Download Excel** button on the message.

## Revision log

| Date | Change |
|---|---|
| 2026-05-24 | Created — standard S2T mapping shape + worked CRM→C360 example. |
