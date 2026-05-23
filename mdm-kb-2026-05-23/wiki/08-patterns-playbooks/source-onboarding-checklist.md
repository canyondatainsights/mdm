# Source Onboarding Checklist

When a new source system needs to feed MDM, the temptation is to start with the technical integration — set up the ingest, build the silver mapping, map to landing. Resist. The work that prevents pain happens *before* a row arrives.

This is the checklist a senior architect runs through before approving a new source for MDM consumption. Adapt to your context; the spirit matters more than the literal items.

## Phase 1 — Discovery (before any technical work)

### Business context

- [ ] **What domain does this source contribute to?** Customer? Supplier? Product? If it spans domains, where does each piece go?
- [ ] **Why is this source being added now?** Business driver. If you can't articulate the driver in one sentence, the source probably isn't ready to be onboarded.
- [ ] **Who's the source-system owner?** Name, email, organization. The person you'll call when the extract breaks.
- [ ] **Who's the data owner for the records in this source?** Not the source-system owner — the business accountability owner. May be the same person; may not.
- [ ] **What downstream consumers will benefit?** If no one consumes it, why are we adding it?
- [ ] **What's the lifecycle status of this source?** New strategic system being adopted? Legacy being deprecated? Acquired-company system to be merged out within a year? Affects how much we invest.

### Legal and compliance

- [ ] **What lawful basis covers the data in this source?** (For PII.) Consent? Contract? Legitimate interests? Confirm with DPO before adding to MDM.
- [ ] **Are there special category data fields?** (Health, biometrics, religious, political, etc.) Higher protection requirements.
- [ ] **What jurisdictions are the subjects in?** Affects retention rules, transfer mechanisms, applicable rights.
- [ ] **Are there contractual data-use restrictions?** Acquired data often comes with restrictions on use beyond the original purpose. Read the contract.
- [ ] **Is consent management already in place for this source?** Where are consents captured? Stored? Withdrawable?
- [ ] **Retention requirements?** Source-system retention, regulatory retention. MDM retention can't be shorter than required retention.

## Phase 2 — Source profiling

Do this against a representative sample (or full extract if feasible) before designing anything.

### Structural

- [ ] **Available extract mechanism?** Files, CDC, API, database link.
- [ ] **Schema documented?** Get the source-system schema. Compare to documented — they always differ.
- [ ] **Primary key?** Reliable? Stable? Or a surrogate that changes if records are re-keyed?
- [ ] **Timestamp columns?** `created_at`, `updated_at`, `extracted_at`. Reliable? Time zone documented?
- [ ] **Record volume?** Total. Daily change rate. Peak rate.
- [ ] **Record arrival latency?** When the source-system event happens, how long until we can see it?

### Content

- [ ] **Run a profile.** See [`../03-data-quality/profiling-and-scorecards.md`](../03-data-quality/profiling-and-scorecards.md).
- [ ] **Completeness — which fields are populated, what percentage?**
- [ ] **Value distributions — top values, unexpected ones.**
- [ ] **Pattern conformance — phones, emails, postal codes.**
- [ ] **Placeholder values — `TEST`, `UNKNOWN`, single-quote characters.**
- [ ] **Cross-column consistency — state matches country, postal matches city.**
- [ ] **Apparent duplicates within the source.** Common in CRM data.

### Quality scoring

- [ ] **What's the source's incoming quality scorecard?** Score per DQ dimension. This is the evidence base for conversations with the source-system owner about upstream improvements.
- [ ] **Are there known quality issues you can't fix downstream?** Some quality problems must be fixed at the source; document which.

## Phase 3 — Design

### Mapping

- [ ] **Source schema → silver schema mapping.** Column-by-column. With transformation notes.
- [ ] **Cleansing decisions.** What gets cleansed in silver, what's left for MDM staging. Document in an ADR if non-trivial.
- [ ] **Reference data joins.** Which source codes need crosswalk lookup? Are the crosswalks in place in Reference 360?
- [ ] **Validation rules.** Which records pass to MDM, which go to quarantine, which fail hard?
- [ ] **Source attribution.** Where in the landing table is the source-system identifier?

### MDM-specific

- [ ] **Which MDM landing table?** New or existing?
- [ ] **Source ranking and trust scores.** Where does this source rank vs existing sources for each column? Implications for survivorship — write down what's expected.
- [ ] **Match rule applicability.** Do existing match rules cover the records from this source, or does it need new rules?
- [ ] **Expected match volume.** Approximately how many of these records will match existing MDM records? Affects review queue volume.

### Operations

- [ ] **Schedule.** Hourly, daily, real-time? Aligned with downstream consumption SLA.
- [ ] **Failure modes.** What happens if source extract fails? Stale data alert thresholds? Recovery process?
- [ ] **Monitoring.** What metrics are published per source? Alert thresholds?
- [ ] **Owner runbook.** Source-specific operational guide.

## Phase 4 — Build and test

- [ ] **Bronze ingest implemented and tested.**
- [ ] **Silver pipeline implemented with DQ rules.**
- [ ] **Quarantine flow tested with deliberately-bad records.**
- [ ] **MDM landing tested with a sample load.**
- [ ] **MDM staging tested — cleansing functions, match key generation.**
- [ ] **Match behavior validated against a known-good test set.** Critical step. Do not skip.
- [ ] **End-to-end orchestration tested.**
- [ ] **Recovery and replay tested.** Re-run with a known input twice; verify idempotency.

## Phase 5 — Pre-production validation

- [ ] **Shadow run for a representative period.** Run the full pipeline in non-prod against a copy of source data. Compare match outcomes to expectation.
- [ ] **Review queue volume from shadow run.** Will stewards be able to absorb the expected steady-state volume? If not, threshold tuning before launch.
- [ ] **Steward training.** Stewards know what records from this source look like, what the expected match patterns are, what to escalate.
- [ ] **Consumer notification.** Downstream consumers know the source is being added; have an opportunity to flag concerns.
- [ ] **Go-live runbook.** Step-by-step for production cutover. Including rollback procedure.

## Phase 6 — Go-live and stabilization

- [ ] **First production load monitored closely.** Architect on standby.
- [ ] **Daily metric review for two weeks.** Match rates, quality scorecards, review queue depth.
- [ ] **Capture lessons learned.** Update this checklist if you found gaps.
- [ ] **Retire workarounds.** Any "temporary" workarounds from go-live get tickets to fix.

## Anti-patterns to refuse

If any of these apply, push back before onboarding:

- **"We just need to get the data in; we'll figure out quality later."** No. Quality decisions deferred are quality decisions deferred indefinitely.
- **"The source-system owner is too busy to engage."** Without engagement, you'll be debugging their data alone for years. Get the engagement or delay onboarding.
- **"We don't have time for a profile; the data is fine."** Every program that says this ends up doing emergency cleanup.
- **"Just point MDM at the source directly; we don't need silver."** You need silver — at minimum for the DQ enforcement and quarantine routing.
- **"Add it as a new source but use existing match rules unchanged."** Maybe. Validate against a sample first. New sources often need new rules.
- **"We'll worry about retention later."** Retention not specified means retention default. Default may be wrong for compliance.

## Sources

- Architect's accumulated practice.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
