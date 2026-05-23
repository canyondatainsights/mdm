# Cleansing and Standardization

Cleansing removes noise. Standardization converts variation to canonical form. They overlap in practice but it helps to keep them conceptually separate.

## Cleansing operations

**Trim and case normalize.** Strip leading/trailing whitespace; convert to consistent case. The boring first step every time. Be careful with case for fields that should preserve it (names — McDonald vs MCDONALD vs Mcdonald are all valid; the matching engine handles case-insensitivity, but display should preserve the input where possible).

**Strip non-printable characters.** Source extracts often carry control characters, BOMs, embedded newlines. They break downstream parsing silently.

**Remove or replace placeholder values.** `XXX`, `N/A`, `n/a`, `Not Available`, `999-99-9999`, empty quotes, single space, `Test`, `Customer`, `Unknown` — every domain has its own set of "the user typed something to get past a required field" values. Identify and convert to NULL or route to reject.

**Collapse internal whitespace.** "John  Smith" with two spaces should usually become "John Smith". This matters more than you'd think for matching.

**Strip punctuation from identifiers.** Tax IDs, phone numbers, SSNs typically should not carry punctuation in stored form. Display formatting is a separate concern.

**Decode HTML entities, fix encoding errors.** Especially from web-scraped or legacy-ASCII sources. "Mc&#x27;Donald" becomes "Mc'Donald".

## Standardization operations

**Name standardization.** This is the hard one. Splits into parsing (the structural work — see below) and value normalization. Value normalization includes:

- Common abbreviations to canonical form: Bob → Robert, Bill → William, Liz → Elizabeth. The trick is *when* — for display you want Bob, for matching you want Robert. The cleanse step usually emits both: a *display* version and a *match-key* version.
- Suffix handling: Jr, Junior, III, Sr → normalize the form.
- Title handling: Mr, Mrs, Ms, Dr, Prof — extract to a separate field, don't leave in the name.
- Honorific suffixes: MD, PhD, Esq → extract.

**Address standardization.** Almost always done by an address verification engine (see [`address-verification.md`](address-verification.md)). DIY address standardization is a tar pit. Use a licensed engine.

**Phone standardization.** Strip everything that isn't a digit or leading `+`. Normalize to E.164 (`+1XXXXXXXXXX` for US) where possible. Country detection is the hard part for inputs without `+` — you need a country attribute on the record or a default assumption documented.

**Email standardization.** Lowercase the entire address. Remove leading/trailing whitespace. Reject obvious garbage (no `@`, multiple `@`, invalid TLD). Don't try to be clever about plus-addressing (`foo+marketing@gmail.com` and `foo@gmail.com` are technically the same Gmail mailbox, but standardizing them together is risky — what if it's not Gmail? What if it's the user's intentional segmentation?).

**Date standardization.** Convert all to ISO 8601 (`YYYY-MM-DD`) for storage. Parse heterogeneous input formats explicitly — never let the database autodetect, because `01/02/2025` is ambiguous (Jan 2 or Feb 1 depending on locale).

**Currency standardization.** Amounts in a single canonical currency for matching, conversion rates effective-dated. Store original currency and amount separately from converted amount.

## Parsing — decomposing compound fields

**Name parsing.** Splitting "Dr. John A. Smith Jr." into Title + First + Middle + Last + Suffix. Informatica's Name Parser (and IDQ's name parsing mapplets) handle the common cases. Edge cases are abundant: hyphenated last names (Smith-Jones), particle prefixes (van der Berg, de la Cruz, O'Reilly), single-name individuals (mononyms), Asian name order (Family-given vs given-family), name + role conflation ("John Smith, CEO" — is "CEO" part of the name?).

Defensive practice: preserve the original full name as a separate column alongside the parsed components. When matching gets weird, you can re-parse.

**Address parsing.** AV engines handle this. The Verifier asset in IDQ wraps Informatica AV.

**Compound identifier parsing.** "JOHN_SMITH_19850101_US" — splits into first name, last name, DOB, country. Common in legacy mainframe extracts. Document the format, parse explicitly.

## Reference tables — the cleansing workhorse

A reference table is a managed lookup. In IDQ, you create reference tables for:

- **Valid values lists.** Valid country codes, valid currency codes, valid business unit codes.
- **Variant-to-canonical mappings.** "USA"/"United States"/"US"/"U.S.A." → all map to canonical "US".
- **Common abbreviation expansions.** "Inc"/"Incorporated", "Corp"/"Corporation", "LLC"/"L.L.C.", "Ltd"/"Limited" for company name normalization.
- **Stop words and noise patterns.** Words to remove or down-weight in matching ("the", "and" — though for company names these matter, so be careful).

Reference tables can be loaded from Reference 360 in modern setups. The dance is: Reference 360 owns the truth, IDQ has a synced copy for runtime performance.

## Standardization for matching vs for storage

This is a distinction worth being explicit about. Two outputs from cleansing:

**Standardized-for-storage.** What you persist in the staging table and ultimately the base object. Should be normalized but preserve enough fidelity for human readability. Mixed-case names, formatted phones, full street names.

**Match-key fields.** Computed columns used only by the match engine. Aggressively normalized: uppercase, no punctuation, abbreviations expanded, possibly Soundex'd or metaphone-encoded. Not displayed.

Both can come out of the same cleanse step but they have different end uses. In Informatica MDM staging, both are columns on the staging table — the business columns and the match columns.

## The Verifier asset (IDQ-specific)

Informatica's Verifier in IDQ is the address-verification wrapper. Configured per country, returns standardized address components, deliverability status, and reference codes. See [`address-verification.md`](address-verification.md).

## What not to do

- **Don't write your own address parser.** It will be wrong in ways you don't notice for years.
- **Don't drop data silently.** Failed cleansing routes to a reject or quarantine table. Stewards review and decide.
- **Don't standardize so aggressively that match rules can't distinguish legitimate variation.** If everyone named "Robert", "Bob", "Bobby", and "Rob" gets normalized to "Robert", you lose the ability to match a Bob who really is a different person from a Robert with the same last name and address.
- **Don't put cleansing logic only in IDQ mapplets and only in Databricks notebooks.** Pick one home for each piece of logic. Duplicated logic drifts.

## Sources

- docs.informatica.com — *Informatica Data Quality and Profiling*, *Data Quality Using Cleanse and Standardization Techniques*.
- Informatica AV documentation referenced for parser behavior.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
