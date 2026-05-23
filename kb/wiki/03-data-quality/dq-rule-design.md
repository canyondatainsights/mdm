# DQ Rule Design

Data quality rules are code. They have authors, versions, tests, and dependencies. They get reviewed, deployed, and deprecated. Teams that treat them as casual configuration build a graveyard of duplicate, ambiguous, undocumented rules that no one trusts. Teams that treat them as code build a rule library that compounds in value.

This page is the architect's opinion on how to design rules so they don't rot.

## Anatomy of a good rule

Every rule should have:

- **A clear name.** `customer_email_must_be_unique` is good. `Rule_47b` is not. Names appear in scorecards, alerts, and steward exception queues — they should be self-documenting.
- **A scope.** Which entity, which attribute, which data source(s)? The rule applies to *customer.email* on the silver layer for the CRM source. Be specific.
- **A predicate.** The logical condition. Returns true (passing) or false (failing). Should be expressible in plain English.
- **A severity.** Critical / High / Medium / Low. Critical means the pipeline halts on violation; low means surface the metric and let a steward decide.
- **A remediation.** What to do when the rule fires. Reject? Quarantine? Auto-fix with a default? Page a steward? Just count?
- **An owner.** The role responsible for the rule's existence and accuracy. Usually a data steward or domain SME.
- **A reason for existence.** Why does this rule matter? What business risk does it mitigate?

Rules without owners and without a reason for existence become orphans. They run, they fail, no one acts, and eventually someone disables them and forgets why.

## Categorization

Group rules by dimension (see [`profiling-and-scorecards.md`](profiling-and-scorecards.md)):

- **Completeness rules.** Field is populated, required combinations are present.
- **Conformity rules.** Value matches a pattern, format, or enumeration.
- **Consistency rules.** Two or more values agree (state matches country, sum matches total).
- **Accuracy rules.** Value reflects reality. Often reference-data-driven.
- **Uniqueness rules.** Value is unique where it should be.
- **Timeliness rules.** Data is fresh enough.

A balanced rule library has rules across all six dimensions. A typical mistake: 80% of your rules are conformity (regex-based pattern checks). That's the easy category. Real data problems hide in consistency and accuracy.

## Naming convention

Adopt one and stick to it. A workable convention:

```
<entity>_<attribute>_<rule-type>_<qualifier>
```

Examples:
- `customer_email_format_valid` — conformity
- `customer_email_unique_per_customer` — uniqueness
- `customer_state_consistent_with_country` — consistency
- `supplier_taxid_present_for_non_individual` — completeness (conditional)
- `customer_address_verifies_to_delivery_point` — accuracy

A rule's name should let you predict what it does without opening it.

## Parameterization

A common pattern that prevents rule sprawl: parameterize.

Bad — a rule per source for the same logic:

```
customer_email_format_valid_crm
customer_email_format_valid_erp
customer_email_format_valid_marketing
```

Good — one rule, applied to multiple sources via configuration:

```
customer_email_format_valid    [applies to: CRM, ERP, Marketing]
```

Same logic, applied wherever it's relevant. When email format expectations change, you change one rule, not three.

The IDQ/CDQ rule designer supports parameter inputs to rules. Use them. Same for thresholds: a rule's pass/fail threshold should be a parameter, not a hard-coded value. That way you can have stricter thresholds in customer master than in prospect data without duplicating the rule.

## Composability

Rules should compose. A "valid customer" rule is the AND of:

- email is valid (or null if not required)
- phone is valid (or null if not required)
- at least one contact method is present
- name is present
- country is in valid set
- if country == US, state is in valid US state set
- if country == US, postal code matches US format

You don't write one massive monolithic rule. You write the components and compose. The benefits:

- When a component fails, you know exactly which one. The steward sees "email format invalid" not "valid customer rule failed".
- Components are reusable across entities (the email rule applies to customer and supplier).
- Components can be unit-tested in isolation.

## Testability

Every rule should have a test case set: a small table of inputs and expected outputs.

Example for `customer_email_format_valid`:

| input | expected |
|---|---|
| `foo@bar.com` | pass |
| `foo+tag@bar.co.uk` | pass |
| `FOO@BAR.COM` | pass |
| `foo@bar` | fail (no TLD) |
| `foo@` | fail (no domain) |
| `@bar.com` | fail (no local) |
| `foo bar@bar.com` | fail (space) |
| `null` | depends on completeness rule (separate concern) |
| `` (empty string) | fail or pass per policy |

Run the tests when you change the rule. CI/CD for DQ rules is a real thing; CDQ supports versioning and promotion of rule assets between environments.

## Severity and remediation

Map severity to action explicitly:

| Severity | What it means | Pipeline action |
|---|---|---|
| Critical | Data is wrong enough that downstream use is dangerous. | Halt or reject record. Page on-call. |
| High | Data is questionable; should not be promoted to gold/MDM without review. | Quarantine record. Alert steward. |
| Medium | Data has a problem but the record is still usable. | Route to review queue. Run-time decision. |
| Low | Surface as a metric for trending; no immediate action. | Count, scorecard, no alert. |

Most rules should be Medium or Low. If everything is Critical, you've made the alert channel useless. Critical is reserved for "this never happens in correct data and if it does we want to know now."

## Reference-data-driven accuracy rules

The most valuable rules check against reference data:

- Country code is in the valid set.
- Currency code is active as of the transaction date.
- Tax ID format matches the country's expected format.
- Postal code prefix matches the city.
- Phone number country code matches the country attribute.

These are all "accuracy" rules under the DAMA framework. They need maintained reference data — which is why [`02-informatica-mdm/reference-360.md`](../02-informatica-mdm/reference-360.md) exists.

## Exception handling

When a rule fails, the record goes somewhere. The options:

- **Reject** — record doesn't enter the next layer. Logged for review.
- **Quarantine** — record enters a parallel table; can be reprocessed after fix.
- **Soft fail** — record enters with a flag indicating the failure. Downstream consumers can decide.
- **Auto-correct** — apply a default or computed value, flag that it was corrected.

Reject is appropriate for catastrophic violations (missing primary key, fundamentally malformed). Soft fail is appropriate for most quality issues — let the record through but mark it. Quarantine is appropriate for "we want to keep this but it shouldn't propagate yet". Auto-correct is appropriate for narrow, well-understood cases (trim whitespace, lowercase email) — *never* for semantic decisions (don't auto-correct "United States" to "US" without explicit standardization rule provenance).

The default mistake is to silently drop or to silently let through. Neither is OK.

## Rule lifecycle

- **Proposal.** Someone identifies a quality issue. Open a ticket: "Customer emails sometimes have leading whitespace; propose a trim cleanse rule + a `customer_email_no_leading_whitespace` validation rule."
- **Draft.** Author writes the rule, the tests, the documentation.
- **Review.** Steward and domain SME approve. Verify the rule does what it says, against a realistic sample.
- **Pilot.** Run in a non-prod or in shadow mode against prod (results recorded, no enforcement). Measure pass/fail rates.
- **Promote.** Enable in production at agreed severity.
- **Monitor.** Track pass rate over time. Investigate anomalies.
- **Deprecate.** When a rule is no longer needed, mark deprecated; remove after a grace period.

This is just regular software development discipline applied to data quality rules. Most teams skip half of it; the half they skip is the half that prevents rule rot.

## Sources

- DAMA-DMBOK2 — DQ framework.
- docs.informatica.com — IDQ rule and mapplet documentation.
- Architect's accumulated practice; not vendor-doctrinal.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
