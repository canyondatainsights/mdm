# ADR-NNN: [Short noun phrase title]

> **Use this template by copying it to a new file named `ADR-001-short-title.md`, `ADR-002-...`, etc. Once written, an ADR is never edited (except for typo fixes). Superseded ADRs get a new ADR that references them.**

## Status

[Proposed | Accepted | Deprecated | Superseded by ADR-NNN]

## Date

YYYY-MM-DD

## Context

What is the situation that requires a decision? What forces are at play (technical, business, regulatory, operational)? What constraints exist?

Be specific. Future readers of this ADR (including you in six months) need to understand the world as it was when the decision was made.

## Decision

What did we decide? State it plainly. One paragraph if possible.

## Rationale

Why this option over alternatives? What evidence or reasoning supports it?

## Alternatives considered

What other options were on the table? Why were they rejected?

Each alternative gets a brief description and the reason it was rejected. This is the part future readers will reach for when they're tempted to reverse the decision — they need to know what was already considered.

## Consequences

### Positive

- What gets better.

### Negative

- What trade-offs we accepted.
- What we're committing to maintain.
- What this decision precludes or makes harder.

### Neutral

- What changes that's worth noting but isn't clearly better or worse.

## Implementation notes

Anything specific about *how* to implement, if not obvious. Code structure, naming conventions, configuration values. Keep this section minimal — the ADR is about the decision, not the implementation.

## References

- Links to relevant wiki pages.
- Links to vendor documentation.
- Links to discussion threads or tickets.
- Links to prior ADRs (especially ones this supersedes).

---

## Example: ADR-001 — Cleansing split between silver layer and MDM staging

### Status
Accepted

### Date
2026-05-23

### Context
The pipeline feeds Informatica MDM from a Databricks silver layer. Standardization and cleansing can happen in silver (PySpark/SQL) or in MDM's staging cleanse step (built-in functions or IDQ-published web services). Both approaches have merit (see [`../04-pipelines-medallion/staging-for-mdm.md`](../04-pipelines-medallion/staging-for-mdm.md)). The team needs a single, documented standard so the two layers don't drift apart over time.

Forces:
- AV is licensed inside the MDM stack; replicating it silver-side would cost.
- Several non-MDM consumers (BI dashboards, ML feature store) read silver and benefit from cleansing happening there.
- The team's strongest skill set is PySpark/SQL, not IDQ rule authoring.

### Decision
General cleansing (trim, case, format, dedup-within-source, basic validation, reference data joins) happens in silver. MDM-specific cleansing (match-key generation, name parsing tuned for SSAName3, address verification) happens in MDM staging cleanse.

### Rationale
This split aligns each layer's work with where its expertise and licensing sit. Silver is the cleansing layer for the broad data estate; MDM staging is the MDM-specific finishing step.

### Alternatives considered
- *All cleansing in silver, MDM staging is pass-through.* Rejected: would require silver-side licensing of AV, and would put MDM-specific match-key generation in a layer where the rest of the team doesn't have MDM context.
- *All cleansing in MDM staging, silver passes through.* Rejected: non-MDM consumers re-implement cleansing or accept worse data.

### Consequences
**Positive.** Each layer's work is clearly bounded. Cleansing logic lives where the expertise lives.

**Negative.** Two teams must coordinate on what "clean" means at the silver/MDM boundary. Drift is a risk; mitigation is a quarterly joint review.

**Neutral.** Reference data is consumed in both layers (silver for validation, MDM staging for additional crosswalks).

### Implementation notes
Silver-layer DLT pipelines include the cleansing expectations documented in [`../06-databricks/dlt-expectations.md`](../06-databricks/dlt-expectations.md). MDM landing-to-staging mappings include AV via the cleanse adapter and match-key generation via published IDQ web services.

### References
- [`../04-pipelines-medallion/staging-for-mdm.md`](../04-pipelines-medallion/staging-for-mdm.md)
- [`../03-data-quality/address-verification.md`](../03-data-quality/address-verification.md)
- Internal ticket DATA-1234
