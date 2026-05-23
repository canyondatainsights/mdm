# Match Rule Tuning Playbook

Match rule tuning is the most iterative work in MDM. There is no "right" configuration that springs fully-formed from a vendor template. There's the configuration that fits *your* data, *your* false-positive tolerance, and *your* steward capacity — discovered by running, measuring, adjusting.

This playbook documents the loop.

## Prerequisites

Before you start tuning, you need:

1. **A profiled understanding of the data.** See [`../03-data-quality/profiling-and-scorecards.md`](../03-data-quality/profiling-and-scorecards.md). Know your data's quality before designing rules.
2. **Standardized data flowing into MDM.** Silver-layer cleansing applied. Names normalized, addresses verified (if AV is silver-side), phones in standard format. Tuning against dirty data is hopeless.
3. **A golden test set.** This is the single most important prerequisite. See below.
4. **Baseline metrics.** How many records, what match rate before tuning, what review queue depth.

## The golden test set

A few hundred record pairs, manually labeled, that you score every rule iteration against. Without this, you're tuning by feel.

Composition:

- **Definite matches** (50-100 pairs): same person/entity, with realistic variation — typos, abbreviations, missing fields, format differences. Should match.
- **Definite non-matches** (50-100 pairs): different people with confusable attributes — same last name, same address (roommates), same email domain. Should not match.
- **Edge cases** (50-100 pairs): genuinely ambiguous. The data isn't sufficient to decide. The rule should surface these to the review queue, not auto-merge or auto-reject.

How to build it:

- Sample from real source data — synthetic data won't expose the messiness of production.
- Have stewards do the labeling, not engineers. Stewards know the domain.
- Include records from each major source. Cross-source pairs are the most informative.
- Refresh quarterly — your data evolves.

This is a privacy-sensitive asset. Handle accordingly; keep it in a tightly-controlled location.

## The metrics that matter

Every rule iteration produces:

- **True positives** — definite matches the rule correctly matched. Want high.
- **False positives** — definite non-matches the rule incorrectly matched. Want low (zero is the ideal but unachievable). Each FP is a steward incident.
- **True negatives** — definite non-matches the rule correctly didn't match. Want high.
- **False negatives** — definite matches the rule missed. Want low. Silent rot.
- **Edge cases routed to review queue** — want all of them in the review queue (not auto-merged, not silently dropped).
- **Auto-merge rate** — percentage of incoming records that auto-merge. Higher is more efficient but riskier.
- **Review queue rate** — percentage routed for steward review. Drives steward workload.

Track these per iteration. The improvement curve over multiple iterations is what tells you whether you're converging.

## The tuning loop

```
        ┌─────────────────────────────┐
        │ 1. Run rules against        │
        │    golden test set + sample │
        └─────────────────────────────┘
                       │
                       ▼
        ┌─────────────────────────────┐
        │ 2. Measure metrics          │
        │    (TP, FP, TN, FN, etc)    │
        └─────────────────────────────┘
                       │
                       ▼
        ┌─────────────────────────────┐
        │ 3. Analyze failures         │
        │    - Why did this FP happen?│
        │    - Why did this FN happen?│
        └─────────────────────────────┘
                       │
                       ▼
        ┌─────────────────────────────┐
        │ 4. Adjust ONE thing         │
        │    threshold, weight, rule  │
        └─────────────────────────────┘
                       │
                       ▼
        ┌─────────────────────────────┐
        │ 5. Re-run                   │
        │    Did it improve?          │
        │    Did it regress elsewhere?│
        └─────────────────────────────┘
                       │
                       └──── back to 1 ────┐
                                           │
        Loop until improvement plateaus.   │
        Then expand the test set.          │
```

The "adjust ONE thing" discipline matters. Tune one variable per iteration. Multiple simultaneous changes make it impossible to attribute the effect.

## Common adjustments

### When you have too many false positives (over-merge)

- **Raise the auto-merge threshold.** More records go to review instead of auto-merge.
- **Increase the weight of high-signal fields** (tax ID, email) and decrease weight of low-signal fields (first name).
- **Add a *required-match* condition** to a stricter field. E.g., "auto-merge only if email matches OR both phone and last name match".
- **Tighten the match purpose.** Different SSAName3 match purposes have different aggressiveness; pick a more conservative one.
- **Add match filters that exclude known-bad records.** Records with `email = 'noreply@*'`, names like "TEST CUSTOMER", placeholder addresses.

### When you have too many false negatives (under-merge)

- **Lower the match threshold.** More pairs are considered.
- **Add additional match rules covering missed patterns.** E.g., a rule that matches on `last_name + DOB + postal_code_prefix` when email and phone are both null.
- **Increase search levels and key levels.** More candidate pairs evaluated. Costs more compute.
- **Expand the blocking strategy.** If you're blocking on Soundex of last name and missing matches where the last name differs significantly (married names, typos), add a secondary blocking strategy (first 3 chars of normalized email, postal code prefix).
- **Loosen overly-strict required-match conditions.**

### When the review queue grows uncontrollably

- **Raise the match threshold (the lower bound).** Fewer pairs reach the review queue.
- **Auto-merge more aggressively** (raise the auto-merge threshold but lower the no-match threshold). Pairs that were "review me" become either "auto-merge" or "no match".
- **Profile what's in the queue.** Often a single bad pattern dominates — a source-system data quality issue is producing thousands of borderline pairs. Fix the source-side issue.
- **Auto-resolve obvious cases.** Pairs with one record explicitly marked "deleted" or "merged elsewhere" in the source — auto-resolve.

### When matching is too slow

- **Add or strengthen blocking.** Fewer candidate pairs.
- **Lower search levels and key levels.** Fewer comparisons per blocking group.
- **Profile to find skew.** A single common surname (a Soundex bucket with millions of records) can dominate runtime. Add a secondary blocking key to break it up.

## Threshold sweep — a structured technique

When you don't have a good intuition for where thresholds should sit, do a sweep:

- Run the rule with auto-merge threshold = 0.70, 0.75, 0.80, 0.85, 0.90, 0.95.
- For each, measure FP, FN, queue volume.
- Plot the curves.
- The right threshold is where the marginal cost of FP equals the marginal cost of FN, given your operational constraints.

For most customer master programs, the auto-merge threshold ends up between 0.85 and 0.92. Outside that range, you probably have a different problem.

## The "data is the problem" diagnostic

Before more rule tuning, check whether the rules are good and the data is bad:

- **Many FPs traceable to a single placeholder value.** Add it to the blacklist.
- **Many FNs traceable to one source's poor data quality.** Improve silver-layer cleansing for that source.
- **Match performance suddenly degrades.** A source-system change is feeding lower-quality data. Investigate source-side.
- **Trust scores skewing wrong.** A source you ranked highly is actually feeding poor data. Re-rank.

Often the most productive "rule tuning" session ends with no rule changes and one ticket to fix a source.

## Stewardship feedback loop

Stewards see the false positives (when they unmerge) and false negatives (when they manually merge records the system missed). Capture both:

- **Every unmerge** should be tagged with reason and the rule that produced the original auto-merge. The rule is a candidate for adjustment.
- **Every manual merge** should be tagged with the attributes that connected the records. If 50 stewards manually merged on the same pattern, the rule should auto-match it.

Without this feedback loop, tuning loses ground over time as the data evolves but the rules don't.

## Cadence

- **Initial tuning:** 3-5 iterations over 4-8 weeks to reach a usable initial state.
- **Stabilization:** weekly review for the first 8-12 weeks post-launch.
- **Steady state:** monthly review of metrics; quarterly review for adjustments; annual major review.
- **Trigger-driven:** any time a new source is added (see [`source-onboarding-checklist.md`](source-onboarding-checklist.md)), any time a source's data quality changes materially, any time the steward queue or business metrics drift.

## Sources

- Informatica Tech Tuesdays: *MDM SaaS Match and Merge Best Practices*.
- LumenData blog: *Match, Merge & Survivorship in Informatica MDM SaaS*.
- Architect's accumulated practice — the loop and the metrics framework.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
