# Reference 360

Reference data is the data that classifies other data. Country codes, currency codes, unit-of-measure codes, internal classifications, chart-of-accounts segments, customer segments, supplier categories. It's the lookup-table data that every system has, that everyone takes for granted, and that quietly causes more cross-system reconciliation pain than any other category.

Reference 360 is Informatica's managed solution for this. It is an IDMC service, available as a standalone or as a companion to Customer/Supplier/Product 360.

## Why dedicated reference data management matters

The unsexy truth: reference data is harder than it looks, and the consequences of getting it wrong are everywhere.

- **Code sets diverge across systems.** ERP uses ISO 3166-1 alpha-2 country codes. CRM uses three-letter ISO codes. The legacy mainframe uses internal numeric codes inherited from 1987. The data lake has whatever was scraped last. None of them are wrong; they're just different. Without a crosswalk, every join across systems requires bespoke mapping.
- **Reference data has effective dates.** Codes change. CHF moved to a new fineness in 2015. The CFA franc devalued in 1994. Croatia adopted the EUR in 2023. ISO 3166 has revised country codes; your historical transactions still reference the old ones.
- **Hierarchies exist.** Country roll up to regions. Cost centers roll up to departments roll up to business units. Industries roll up to sectors. Reference data isn't flat.
- **Versioning matters.** A re-org changes the cost-center hierarchy on April 1. Reports for Q1 should reflect the old hierarchy; reports for Q2 the new one. Both must be queryable.

## What Reference 360 manages

- **Code sets** — flat lists of valid codes with descriptions, status (active/inactive), effective dating.
- **Hierarchies** — parent-child relationships within a code set. Multiple hierarchies per code set are supported (a "legal" country hierarchy vs an "operational regions" hierarchy).
- **Crosswalks** — mappings between code sets in different systems. Maps "US" (ISO alpha-2) to "USA" (alpha-3) to "840" (numeric) to "001" (your legacy code).
- **Attributes** — additional properties beyond code + description. Currency code carries decimal places, symbol, etc.
- **Versions and effective dates** — both system time and valid time.
- **Workflow** — propose change, review, approve, publish. Reference data changes are not casual.

## Integration patterns

**Reference 360 as the source-of-record.** Reference data lives in Reference 360. Consuming systems pull from Reference 360 via API or replicated extract. New reference values are added in Reference 360 first; consuming systems consume the change. This is the cleanest pattern but requires consuming systems to be Reference-360-aware.

**Reference 360 as the reconciliation hub.** Each source system retains its own code sets. Reference 360 maintains crosswalks between them and is consulted whenever cross-system reporting or integration needs to translate. More realistic for environments with packaged applications you can't change.

**Reference 360 as the governance layer.** Each system owns its own data; Reference 360 holds the documented standard and reports on conformance. Useful as a starting point in regulated industries where the standards exist but enforcement is weak.

Most enterprises run a mix — strict source-of-record discipline for high-value reference data (currency, country, regulated classifications), reconciliation pattern for legacy systems, governance pattern for the long tail.

## What lives in Reference 360 vs what doesn't

Reference 360 is for *reference data* — values used to classify or describe other entities. It is **not** for:

- **Master data** (customers, suppliers, products). Use Customer/Supplier/Product 360.
- **Transactional data** (orders, invoices, events). Use your transactional systems and warehouse.
- **Configuration data** (application settings, feature flags). Use a config service.

A useful test: *Does this data point to a real-world entity (master), record an event (transaction), or classify/describe other data (reference)?* If the third, it belongs in Reference 360.

## Schema example

A typical Reference 360 code set:

```
CodeSet: Country
  Code: US
  Name: United States of America
  ShortName: United States
  ISOAlpha3: USA
  ISONumeric: 840
  Region: North America        ← FK into Region code set
  EUMember: false
  EffectiveFrom: 1776-07-04
  EffectiveTo: NULL
  Status: Active
```

A crosswalk:

```
CrosswalkSet: Country_ERP_to_CRM
  SourceSystem: SAP_ERP
  TargetSystem: Salesforce_CRM
  SourceCode: "01"
  TargetCode: "USA"
  SourceCodeSet: Country
  TargetCodeSet: Country
  EffectiveFrom: 2018-01-01
  EffectiveTo: NULL
```

A hierarchy:

```
Hierarchy: Geographic_Operating_Regions
  Parent: AMER
    Child: NA
      Child: US
      Child: CA
      Child: MX
    Child: LATAM
      Child: BR
      Child: AR
      ...
```

## Common pitfalls

- **Letting reference data live in spreadsheets.** It will be out of sync within weeks. Bring it into Reference 360 or accept that you don't have governed reference data.
- **No effective dating.** A currency conversion at today's rate is meaningful; at last year's transaction date is meaningful; at "whichever rate happened to be in the lookup when the report ran" is dangerous. Effective dating fixes this.
- **One global owner for all reference data.** No single team understands all reference data. Distribute ownership by domain (finance owns chart-of-accounts segments; HR owns job codes; marketing owns customer segments) with central governance for shared sets.
- **Treating crosswalks as one-time.** Source systems get reorganized, codes get retired, new codes get added. Crosswalks decay if no one maintains them.

## Sources

- Informatica product page: Reference 360.
- aiDataWorks blog: *Informatica Cloud MDM Reference 360 Solutions* (referenced for SaaS security and architecture description).

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
