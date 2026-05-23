# Right to Erasure in MDM

This is the hardest GDPR requirement to implement in MDM. The golden record is the *consolidation* of contributions from many sources, persisted with lineage, replicated downstream. Erasing a person isn't a DELETE statement — it's a coordinated propagation across an entire data estate, with exceptions for data you're legally required to keep.

This page documents the patterns that work. The architect's honest opinion: if your right-to-erasure runbook hasn't been tested end-to-end with a sample request, it doesn't work yet.

## What Article 17 says

A data subject has the right to obtain erasure of personal data concerning them without undue delay, where one of the following applies:

1. The personal data is no longer necessary for the purposes for which it was collected.
2. The data subject withdraws consent (where consent was the lawful basis) and there is no other lawful basis.
3. The data subject objects to processing and there are no overriding legitimate grounds.
4. The data was processed unlawfully.
5. Erasure is required for compliance with a legal obligation.
6. The data was collected from a child without proper consent (Article 8 context).

And the request can be **refused** where processing is necessary for:

- Exercising the right to freedom of expression and information.
- Compliance with a legal obligation (e.g., tax records, regulatory retention).
- Reasons of public interest in public health.
- Archiving in the public interest, scientific or historical research, statistical purposes (where erasure would seriously impair).
- Establishment, exercise, or defense of legal claims.

The combination of grounds-for-erasure plus exemptions means **every request must be evaluated, not auto-fulfilled**. A blanket "user pressed delete, we delete everything" workflow is almost certainly non-compliant in either direction — either over-deleting data you're legally required to keep, or under-deleting data the subject is entitled to have erased.

## The MDM challenge

Why this is hard in MDM specifically:

- **The golden record is derived.** Erasing the BVT row is meaningless if the contributing source records remain.
- **Source records often must persist.** The contributing source system may have its own legal retention obligations (tax records, customer service records, transaction history) that the erasure right doesn't override.
- **Lineage is part of the audit trail.** XREF, history, and audit tables capture *why* the golden record looks the way it does. Naive erasure breaks this.
- **Downstream replicas are everywhere.** The BVT has been replicated to a warehouse, materialized to BI tools, exported to marketing platforms, possibly trained into ML models. Erasure has to propagate.
- **Backups and archives.** Off-site backups containing the data. Practical guidance: data subject to erasure is not actively restored from backup; backups are aged out per retention policy.

## The realistic erasure pattern

A pragmatic, defensible approach has these steps:

### 1. Receive and verify the request

- Capture the request through a documented channel.
- Verify the requester is the data subject (or authorized representative). Identity verification proportionate to the sensitivity of the data — not so onerous as to be a barrier, not so loose as to be a vector for impersonation.
- Acknowledge receipt within a few working days. The one-month clock has started.

### 2. Search and consolidate

- Find every record relating to the subject across systems. MDM helps here — the XREF gives you the cross-system fingerprint. A single search by the resolved Party ID returns contributions from every connected source.
- Compile a list of: MDM records (BVT + history + XREF), source-system records (per contributing source), warehouse/lake replicas, downstream consumer records (CRM, marketing, support), backups.

### 3. Evaluate and apply exemptions

- For each record set found, evaluate: is there a legal basis to retain (regulatory retention, ongoing contract, legal claim, etc.)?
- Document the evaluation. The data subject is entitled to know what you're erasing and what you're keeping with reasons.
- The output is a *decision matrix* per system: erase, retain (with reason), or partially erase (anonymize / pseudonymize identifying attributes, keep non-identifying transaction history).

### 4. Execute the erasure

Several technical patterns, used in combination:

**Anonymization of the master record.** Rather than DELETE the BVT row, overwrite the identifying columns with anonymized values. The record persists for referential integrity (orders still reference a customer key) but is no longer linkable to a person.

```sql
UPDATE silver.customer_master
SET
  first_name = 'REDACTED',
  last_name = 'REDACTED',
  email = NULL,
  phone = NULL,
  date_of_birth = NULL,
  address_line_1 = NULL,
  postal_code = NULL,
  -- keep: customer_id, country, segment_code (non-identifying)
  erasure_applied_at = CURRENT_TIMESTAMP(),
  erasure_request_id = '...'
WHERE party_id = '...';
```

In Informatica MDM, this means BVT cell overwrite via a steward action or programmatic call. The XREF lineage is preserved but the personal data is gone.

**Source-system erasure.** Push the erasure request to each contributing source system. Each source applies its own erasure (subject to its own retention exemptions) and confirms back.

**Downstream propagation.** The erasure event triggers downstream consumers to apply the same erasure to their copies. Warehouse gold table, BI tool's caches, marketing platform's audience lists. Each consumer system needs an erasure intake API or process.

**Backup handling.** Document a policy: backups are retained per backup retention policy; data is not actively restored from backups for the erased subject. New backups created after erasure don't contain the data (because it's been erased from the live system). This is generally accepted by supervisory authorities as proportionate.

**Audit log.** Every erasure action is logged. The audit log itself is generally retained — its existence is required to demonstrate compliance with the erasure request, even though the underlying data is gone.

### 5. Confirm to the data subject

- Confirm completion within the one-month deadline (or notify of extension and reason).
- Provide the data subject with information on what was erased and what was retained (with reasons).
- The compliance documentation includes: the original consent record (where applicable), the withdrawal/erasure request, the evaluation, and the actions taken. Together these form the audit trail.

## What about the XREF?

The XREF is the cross-reference between MDM golden records and source contributions. It contains the source primary keys, source timestamps, and identifying data. The question: does the XREF need to be erased?

The practical answer (with DPO input): the XREF for an erased subject is anonymized similarly to the BVT. Source-system keys can be retained if they're no longer linkable to a person (the source records they pointed to are also being erased). Cell-level contributions in XREF are overwritten with REDACTED tokens for identifying attributes.

This preserves the audit trail ("there was once a Party with this ROWID_OBJECT, contributed from these sources, erased on this date") without retaining personal data.

## Pattern: the erasure marker

Some implementations add an "erasure marker" column at the Party level. Set to TRUE when the erasure is executed. Every consuming system checks this marker before processing the record. Downstream queries filter out erased records. This adds defense-in-depth against re-emergence — if a stale data extract somehow re-introduces the erased record, the marker prevents reprocessing.

## Pattern: the suppression list

A separate, minimal list of "do not contact / do not process" identifiers (e.g., email hashes) is maintained. When a new record arrives that matches the suppression list, it's quarantined and not added to MDM. This prevents the situation where a customer who erased their data re-appears because the underlying source system added them back from another source feed.

The suppression list is itself personal data (it identifies people who exercised their erasure right) and must be tightly controlled. But its existence is generally seen as a reasonable measure to prevent re-introduction.

## The legitimate retention reason — write it down

Where you're retaining data despite an erasure request, the reason must be documented and supportable. Examples:

- "Subject's transaction history is required for tax compliance under [jurisdiction] for 7 years from transaction date. Retention period expires [date]; data will be re-evaluated for erasure then."
- "Subject is party to an ongoing dispute / open case; data retention is necessary for the establishment, exercise or defense of legal claims. Will re-evaluate when case is closed."

Without this documentation, "we kept it" is an audit finding.

## Anonymization vs pseudonymization

GDPR distinguishes:

- **Anonymized data** — no longer relates to an identifiable person, even in combination with other reasonably available data. Falls outside GDPR scope.
- **Pseudonymized data** — identifying attributes replaced with pseudonyms, but the mapping exists somewhere. Still personal data under GDPR.

In practice, "anonymization" is hard to achieve completely. Re-identification through combination with external data is a persistent risk. Most erasure implementations achieve strong pseudonymization rather than true anonymization, and treat the residual data accordingly.

## What to test

If this runbook has never been exercised, it doesn't work. Suggested test:

1. Create a test subject across multiple source systems (with synthetic data).
2. Let it propagate through silver into MDM and downstream.
3. Submit a simulated erasure request.
4. Execute the runbook end-to-end.
5. Verify in each system: data is erased, audit trail is intact, downstream replicas no longer contain identifying data, backups will age out cleanly.
6. Time it. Was it possible within one month? Where were the bottlenecks?

Then fix the bottlenecks before a real request arrives.

## Sources

- Regulation (EU) 2016/679, Article 17.
- gdpr.eu: *Everything you need to know about the "Right to be forgotten"*.
- nordlayer.com: *The right to be forgotten and data privacy (Articles 17 & 19)*.
- aws.amazon.com Big Data Blog: *Five actionable steps to GDPR compliance (Right to be forgotten) with Amazon Redshift* (referenced for the warehouse-side propagation pattern).
- Architect's accumulated practice.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
