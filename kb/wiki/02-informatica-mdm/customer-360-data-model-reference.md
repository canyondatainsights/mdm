---
vendor: informatica
product: Customer 360
domain: customer
scope: vendor-specific
---

# Customer 360 — Data Model Reference

A practitioner reference for the Informatica **Customer 360** data model: the business
entities, their **field groups**, and the representative **fields** in each. It consolidates
the field-group set documented for Customer 360 SaaS (April 2026) and the Consent Management
extension with the standard C360 party model (see [`customer-360.md`](customer-360.md) and
[`data-model-landing-staging-base-object.md`](data-model-landing-staging-base-object.md)).

> Exact field availability and internal names vary by version (on-prem 10.x vs SaaS on IDMC)
> and by local configuration/extension. Use this as the canonical shape; confirm specifics
> against your environment's metadata.

## Business entities

| Business Entity | Purpose | Notes |
|---|---|---|
| Person | An individual customer (the Party Type = Person) | Carries biographical + contact field groups |
| Organization | A legal entity / B2B customer (Party Type = Organization) | Carries firmographic + contact field groups |

Both are specializations of the abstract **Party**. Contact field groups (Address, Phone,
Email, etc.) attach to either entity, typically one-to-many.

## Field groups (Person business entity)

| Field Group | Purpose |
|---|---|
| General | Core person attributes (name parts, DOB, gender, status) |
| Alternate Names | AKAs, maiden names, aliases |
| Citizenship | Citizenship / nationality |
| Communication Channel Preferences | Opt-in/opt-out per channel (consent) |
| Demographics | Demographic / segmentation attributes |
| Documents | Document records (e.g. ID documents) |
| Education | Educational history |
| Electronic Address | Email and digital contact points |
| Employment | Employer and job-role data |
| Financial | Financial profile attributes |
| Identification | Identity credentials (passport, tax ID, license) |
| Insurance | Insurance policy / coverage data |
| Lifestyle | Lifestyle and interest attributes |
| Loyalty | Loyalty program membership / status |
| Phone Communication | Phone records and preferences |
| Postal Address | Physical mailing addresses |
| Rights Request | Data-subject rights requests (GDPR/CCPA) — consent extension |
| Social Media | Social handles / profiles |
| Specialization | Professional specialization |
| Status | Record lifecycle status |
| Tax Details | Tax identification / classification |

(The Organization entity carries the firmographic equivalents plus shared contact groups; a
**Segment** field group is scoped to Organization in the consent extension.)

## Representative fields by field group

### General (Person)
| Field | Data Type | Notes |
|---|---|---|
| Source Primary Key | String | Source-system natural/business key |
| First Name | String | Match key |
| Middle Name | String | |
| Last Name | String | Match key |
| Full Name | String | Often derived/concatenated |
| Date of Birth | Date | |
| Gender | Lookup | Ref: Gender |
| Marital Status | Lookup | Ref: MaritalStatus |
| Preferred Language | Lookup | Ref: Language |

### Postal Address
| Field | Data Type | Notes |
|---|---|---|
| Default Indicator | Boolean | Flags the default address |
| Address Type | Lookup | Ref: AddressType (home, office, shipping, mailing) |
| Usage Type | Lookup | Ref: AddressUsageType (business, personal) |
| Address Line 1 / 2 / 3 | String | |
| City | String | |
| County | String | |
| State | Lookup | Ref: State |
| Country | Lookup | Ref: Country |
| Postal Code | String | |
| Postal Code Extension | String | |
| Address Status | Lookup | Ref: AddressStatus (active, current, old, changed) |
| Latitude / Longitude | String | Geocode |
| Effective Start / End Date | DateTime | Time-bounded validity |
| Enriched Indicator | Lookup | Ref: AddressEnrichedIndicator (validated, not validated, error) |

### Phone Communication
| Field | Data Type | Notes |
|---|---|---|
| Phone Type | Lookup | mobile, home, work |
| Country Code | String | |
| Phone Number | String | |
| Extension | String | |
| Default Indicator | Boolean | |
| Opt-In Indicator | Boolean | Channel consent (see Communication Channel Preferences) |

### Electronic Address (Email / digital)
| Field | Data Type | Notes |
|---|---|---|
| Electronic Address Type | Lookup | email, web, instant-messaging |
| Email Address | String | Match key |
| Default Indicator | Boolean | |
| Validation Status | Lookup | valid, invalid, unverified |

### Identification
| Field | Data Type | Notes |
|---|---|---|
| Identifier Type | Lookup | passport, national ID, tax ID, driver license |
| Identifier Value | String | Often a match key; may be PII-restricted |
| Issuing Country | Lookup | Ref: Country |
| Valid From / To | Date | |

### Communication Channel Preferences & Rights Request (consent extension)
| Field | Data Type | Notes |
|---|---|---|
| Channel | Lookup | email, phone, SMS, postal |
| Opt-In / Opt-Out | Boolean | Per-channel consent state |
| Consent Source | String | Where consent was captured |
| Consent Timestamp | DateTime | |
| Rights Request Type | Lookup | access, erasure, rectification, portability |
| Rights Request Status | Lookup | received, in-progress, fulfilled, rejected |

## Modeling notes
- **Typed, multi-valued contact groups.** Address/Phone/Email/Identification are one-to-many
  and typed; a `Default Indicator` marks the primary per type.
- **Effective dating.** Many groups carry effective start/end dates; survivorship and BVT
  consider the active record.
- **Lookups.** `Lookup` fields resolve to reference-data code sets (see
  [`reference-360.md`](reference-360.md)).
- **Survivorship.** The Best Version of the Truth (BVT) picks the most trusted value per
  field across sources (see [`match-merge-survivorship.md`](match-merge-survivorship.md)).

## Revision log

| Date | Change |
|---|---|
| 2026-05-24 | Created — consolidated C360 field-group reference for mapping work. |
