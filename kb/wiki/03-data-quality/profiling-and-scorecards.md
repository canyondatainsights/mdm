# Profiling and Scorecards

Profiling is the first thing you do with a new data source. Always. Before you write rules, before you design mappings, before you commit to a match strategy — profile.

The profile tells you what you actually have, as opposed to what the source-system documentation says you have, which in turn is usually different from what the business assumes you have. The gap between these three is where MDM projects go to die.

## What profiling measures

Standard profile output for a column:

- **Row count and non-null count.** Completeness baseline.
- **Distinct values count.** Cardinality. Is this an identifier, a category, a free-text field?
- **Null and blank counts.** Missing-data baseline.
- **Min / max / average length** for strings.
- **Min / max / average value** for numerics.
- **Value distribution** — top N most frequent values and their counts.
- **Pattern distribution** — what character-class patterns appear and how often. (For phones, you might see `999-999-9999`, `(999) 999-9999`, `9999999999`, and `unknown` each as separate patterns.)
- **Inferred datatype.** What looks like a date, a number, an email.
- **Uniqueness percentage.** Useful for candidate-key discovery.

For more advanced profiling: cross-column dependencies (does state always correlate with postal code?), join analysis (which columns join cleanly between tables), and rule conformance (what percentage of values match a defined rule).

## How to read a profile

A few patterns to look for and what they tell you:

**A column with 90% nulls.** Either the field isn't being captured (process gap) or the field is optional and only filled for a subset of records (segmented data). Ask which before designing.

**A "phone" column with 200 distinct patterns.** Free-text entry, no validation. Standardize aggressively before matching.

**A "first name" column where the top value is "Test" or "Customer" or single quote/space.** Data-entry placeholders. Add to a blacklist before matching, or you'll get massive false-positive match clusters.

**A "country" column with US, USA, U.S.A., United States, United States of America, "us" (lower), and 47 other variants for the same country.** Reference data discipline failure. Decide on the canonical form, build a crosswalk, standardize.

**A date column with 5% future dates.** Either data-entry errors or legitimate future-dated records (effective-dated contracts). Need to know which.

**A "tax ID" column with 30% nulls.** May be a multi-country issue (different countries have different ID types — tax ID nullable depending on jurisdiction) or a process gap. Investigate, don't assume.

## Profiling in IDQ vs CDQ

In on-prem IDQ, profiling is done through Analyst Tool or Developer Tool. Results are stored in the Model Repository and can be referenced by rules and scorecards.

In CDQ on IDMC, profiling is a service running over data sources connected via Cloud Data Integration's connection library. Results are surfaced in the Data Quality console.

The mechanics differ; the questions to ask of a profile are the same.

## From profile to rule

The profile is the input to rule design. The pattern:

1. **Run an initial profile** on the raw source.
2. **Identify quality issues** — completeness gaps, format variation, invalid values.
3. **Write rules** that detect each issue. (Don't try to fix everything; first, just detect and measure.)
4. **Run the rules** as a scorecard against the source.
5. **Decide remediation strategy** per issue: cleanse in-pipeline, route to reject, quarantine for review, escalate to source-system owner.
6. **Implement cleansing** for issues that can be fixed automatically.
7. **Re-run the scorecard** to verify improvement.
8. **Track over time** to detect regressions.

The output of this loop is a *baseline quality profile* and a set of *rules* that monitor against that baseline. Both belong in version control (or the Model Repository, which is the IDQ equivalent).

## Scorecards

A scorecard is a curated dashboard of rule results, grouped by domain, with thresholds and trend. The point of a scorecard is to give the business a single number — or a small set of numbers — that answers *"is our data quality good?"*

Typical scorecard structure for a customer master:

| Dimension | Rule | Score | Threshold | Trend |
|---|---|---|---|---|
| Completeness | Customer record has primary email | 87% | 90% | ↓ |
| Conformity | Email matches valid pattern | 99.2% | 99% | → |
| Conformity | Phone matches valid pattern | 78% | 95% | ↓ |
| Consistency | State code matches country | 99.8% | 99% | → |
| Accuracy | Address verifies in AV | 92% | 95% | → |
| Uniqueness | Email is unique per customer | 96% | 100% | → |

Each row points back to a rule definition. Stewards see the rollup; they drill in to see the failing records. The trend column comes from running the scorecard repeatedly over time.

The mistake to avoid: a scorecard with too many rows that no one reads. Better to have 10 rules that matter than 200 rules that get ignored. Start small, add as the program matures.

## Profiling cadence

- **Initial onboarding** — profile every new source thoroughly before integration.
- **Pre-release** — profile after every major source-system release, before promoting to production MDM consumption.
- **Continuous** — automated scorecard runs nightly or hourly against silver/staging tables. Anomalies trigger alerts.
- **Periodic deep profile** — annually, re-run a full profile to catch drift that the focused rules might have missed.

## Sources

- docs.informatica.com — *Informatica Data Quality and Profiling*.
- DAMA-DMBOK2 — DQ dimensions framework.
- emergenteck.in: *What is Informatica Data Quality* (used for IDQ profiling workflow narrative).

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
