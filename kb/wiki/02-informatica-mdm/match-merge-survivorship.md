# Match, Merge, and Survivorship

This is the page most people will ask about. Match-merge-survivorship is the heart of MDM and the source of most operational pain. Get the rules right and the program runs. Get them wrong and stewards drown in queues, the business loses trust in the golden record, and the program quietly dies.

## The three steps, separated

People conflate these. Keep them separated in your head.

**Match.** *Do these two records describe the same entity?* Output: pairs of records with match scores.

**Merge.** *Given that these records match, combine them into one consolidated record.* Output: merged rows, with the source rows preserved in XREF.

**Survivorship.** *For each cell of the consolidated record, which source's value wins?* Output: the BVT — the Best Version of the Truth.

You can match without merging (queue for human review). You always merge with survivorship — they happen together. But it helps to design them as separate concerns.

## Match types

**Exact (deterministic) match.** Two records match if specified fields are exactly equal after normalization. Cheap, simple, deterministic. Right when you have reliable identifiers: tax ID, SSN, customer number assigned by a single source-of-record, email address (with caveats).

**Fuzzy (probabilistic) match.** Tolerates variation — typos, transpositions, phonetic similarity, word reordering, abbreviations, missing or extra punctuation. Informatica uses the SSAName3 engine, which is tuned per language and per *match purpose* (Person_Name, Organization_Name, Address_Part1, Wide_Contact, etc.). Right for names, addresses, and any human-entered text.

**Segment match.** Restricts comparison to a subset (e.g., only compare records within the same country). Used to keep match volume manageable and to enforce business segmentation.

The practical rule: **start with exact match on reliable identifiers, layer fuzzy match on names and addresses for the records that didn't exact-match, use blocking keys to keep volume manageable.**

## Match rule anatomy

A match rule in Informatica MDM consists of:

- **Match columns** — which fields are compared. Often pre-processed (Soundex of last name, standardized phone, etc.).
- **Match purpose** — selects the SSAName3 algorithm and weights tuned for the type of comparison.
- **Search levels and key levels** — control how many candidate pairs are evaluated. Higher levels = more candidates considered = more compute = more potential matches found. Lower levels = faster but might miss matches.
- **Match weights** — how much each compared field contributes to the overall score.
- **Match threshold** — the minimum score above which the pair is considered a match.
- **Auto-merge threshold** — above this score, merge automatically; between the match threshold and auto-merge threshold, queue for steward review.
- **Match filter** — preconditions that must be true for the rule to even apply (e.g., both records have a non-null email).
- **Null match strategy** — how to treat nulls. Options vary: nulls match nothing, nulls match anything, configurable per column.

## Match thresholds — the most-tuned setting

A match rule produces a score for each pair. Typical thresholds in practice:

- **Below ~0.7:** Not a match. Don't even surface to stewards.
- **0.7 to ~0.85:** Possible match. Queue for steward review.
- **Above ~0.85:** Auto-merge.

These are starting points, not gospel. **The actual thresholds depend on your data, your domain, and the cost of false positives vs false negatives.** For customer master in a B2C retailer with frequent customer-initiated typos, you can go fairly aggressive (lower threshold) because over-merge is recoverable and under-merge causes immediate marketing pain. For supplier master in a regulated industry where merging two distinct legal entities is a compliance event, you want the auto-merge threshold high and a large review queue.

## Tuning loop (the actual process)

This is iterative. Anyone who tells you they got match rules right on the first pass either has trivial data or is lying.

1. **Profile first.** Know your data's quality before designing rules. See `wiki/03-data-quality/profiling-and-scorecards.md`.
2. **Standardize upstream.** Names cased consistently, addresses verified, phones in a single format. Doing this in IDQ or in your silver layer before MDM sees the data makes match rules dramatically simpler.
3. **Build a golden test set.** A few hundred record pairs that you have manually labeled "match" / "not a match" / "ambiguous". This is what you score your rules against.
4. **Start conservative.** Tight thresholds. Few auto-merges. Lots of stewardship queue volume initially.
5. **Run, measure, adjust.** Track false positive rate (incorrect auto-merges — recoverable but each one is a steward incident), false negative rate (missed matches — silent rot), and review-queue volume (steward workload).
6. **Loosen gradually.** Lower thresholds incrementally, retest against the golden set.
7. **Re-baseline annually.** Data drifts. New sources arrive. The rule that was optimal a year ago isn't optimal now.

A reasonable rough budget: three tuning iterations to reach a usable initial state, ongoing quarterly review for the first year, annual review thereafter.

## Common match-rule failure modes

- **Over-merging.** Threshold too low, or weights misallocated. Two records that should be separate get merged. The fix is hard because once merged in MDM, you have to *unmerge* and reverse-engineer which contributions belonged to which entity. Informatica supports unmerge through the steward UI; it's not fun.
- **Under-merging.** Threshold too high, or critical match purposes not used. Duplicates persist. Silent — no one notices until marketing sends three of the same letter to one customer.
- **Match storm.** A single bad record (e.g., a placeholder "TEST CUSTOMER" entered repeatedly) matches hundreds of real records. The fix is to add match filters that exclude known-bad values. Also a profiling hint — these values should have been caught in DQ.
- **Slow match runs.** Almost always a search-level/key-level issue or missing blocking. Increase search levels only when you've exhausted blocking optimizations.

## Survivorship — how cells win

When MDM merges two matching records into one, survivorship decides which source's value goes into each cell of the consolidated record. The Informatica precedence is:

1. **Trust score** (if the column is trust-enabled). The cell with the highest current trust score wins.
2. **Validation rules.** Cells that fail validation can be downgraded or excluded.
3. **Source ranking.** If trust is not enabled, you rank source systems and the higher-ranked source wins.
4. **Recency.** If trust and ranking are tied, the more recently updated cell wins.
5. **ROWID_OBJECT.** Final tiebreaker. The record with the higher ROWID_OBJECT wins. This is arbitrary but deterministic.

### Trust scores

A trust score is a per-source, per-column reliability rating. Configured as:

- **Maximum trust** — the score assigned to a fresh contribution from this source for this column.
- **Minimum trust** — the floor.
- **Decay duration** — how long it takes to decay from max to min.
- **Decay function** — linear, slow then fast (concave), fast then slow (convex).

The intuition: a fresh CRM contact record is highly trustworthy for the customer's current phone number, but if it hasn't been touched in three years, the marketing system's recent contact may be more reliable. Trust decay encodes this.

In practice, most teams don't configure trust precisely. They use coarse source ranking (CRM > ERP > marketing tool) and only enable trust on a handful of high-volatility columns (phone, email, address). That's usually fine.

### Survivorship — block survivorship

For some columns, you don't want to mix-and-match cells across sources. The classic example is an address: you want the *whole address* from one source — street, city, postal code — not Street from CRM, City from ERP, ZIP from marketing. Block survivorship treats a group of columns as an atomic unit; the winning source's whole block goes into the BVT.

Configure block survivorship for: addresses (always), name parts (usually — keep first/middle/last together from one source), contact methods (email and phone pairings).

## Sources

- Informatica Tech Tuesdays: *MDM SaaS Match and Merge Best Practices*, *Match and Merge Use-cases*, *C360 SaaS — Automate Match Strategy and Survivorship*.
- docs.informatica.com — *Cell Data Survivorship and Order of Precedence*.
- Informatica Success Accelerator: *MDM Match/Merge*.
- LumenData blog: *How to Get the Golden Master Copy of Your Data using Match, Merge & Survivorship in Informatica MDM SaaS*.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
