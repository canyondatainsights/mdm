# Consent Management

When consent is the lawful basis for processing, the consent itself becomes a first-class data asset. It must be captured, stored, queryable, auditable, granular by purpose, withdrawable, and version-able as your privacy notice evolves. Treating consent as a flag on the customer record is the most common mistake — and the most consequential one when regulators come asking.

## What valid GDPR consent looks like

Article 4(11) defines consent as a *"freely given, specific, informed, and unambiguous indication of the data subject's wishes by which they, by a statement or by a clear affirmative action, signify agreement"*. Each adjective is doing work.

- **Freely given.** The subject must have a genuine choice. Pre-checked boxes, "you must accept to use this service" tying consent to access without genuine alternative, or significant imbalance between subject and controller (employee-employer) all undermine validity.
- **Specific.** Consent for one purpose doesn't cover another. Bundled "I agree to all uses of my data" is invalid. Granular per-purpose consent is required.
- **Informed.** The subject must know who the controller is, what data is processed, for what purposes, and that they can withdraw. The privacy notice they were shown matters.
- **Unambiguous, by clear affirmative action.** Active opt-in. Silence, inactivity, pre-checked boxes do not constitute consent.

Article 7 adds:

- The controller must be able to **demonstrate** that consent was given. This is the audit requirement.
- Consent must be **as easy to withdraw as to give**. One-click in, one-click out.
- Withdrawal **does not affect lawfulness of processing before withdrawal** — but processing must stop going forward (and, depending on context, the data may need to be erased under Article 17).

## What you must store

A consent record, per data subject per purpose:

| Field | Why |
|---|---|
| `subject_id` | Who consented |
| `purpose_id` | What they consented to (specific) |
| `purpose_version` | Which version of the purpose description they saw (informed) |
| `consent_given_at` | When (auditable) |
| `consent_method` | How (web form, paper, in-app, double opt-in email) |
| `notice_version_shown` | Which privacy notice was visible at the moment (informed) |
| `evidence` | Form data, IP, click path, confirmation token, signed document reference |
| `withdrawn_at` | When (if applicable) |
| `withdrawal_method` | How withdrawn |
| `status` | ACTIVE / WITHDRAWN / EXPIRED |

The evidence column is what saves you in an audit. "User clicked the consent button" is not enough. The form data (which boxes were checked), the privacy notice text shown to them at that moment, the timestamp, the source IP — these together demonstrate that consent was given freely, specifically, and informedly. A consent management platform (CMP) is the usual way to capture this consistently.

## Granularity — by purpose, not by data type

A common mistake is recording consent at the data-type level ("user consents to email marketing") rather than the purpose level. Purpose limitation is what matters. Examples of purposes:

- Sending product-update emails relating to existing purchases.
- Sending marketing emails about adjacent product lines.
- Sharing data with named partner for joint promotion.
- Profiling for personalized recommendations.
- Profiling for credit-decision purposes (which has automated-decision implications too).

Each of these gets its own consent record. The customer might say yes to product updates, no to marketing emails, yes to recommendations, no to partner sharing — and your MDM must reflect that.

## The relationship between consent and the master record

The customer's master record in MDM may have many associated consents. A common modeling approach:

```
Customer (BVT)
   │
   ├── Consent
   │     • purpose_id: marketing_email
   │     • status: ACTIVE
   │     • given_at: 2024-03-15
   │
   ├── Consent
   │     • purpose_id: partner_sharing
   │     • status: WITHDRAWN
   │     • given_at: 2024-03-15
   │     • withdrawn_at: 2024-09-01
   │
   └── Consent
         • purpose_id: profiling
         • status: ACTIVE
         • given_at: 2025-01-10
         • notice_version: PN-2025-Q1
```

Consents are time-bounded records linked to the customer. They are *not* attributes on the customer master. Each consent has its own audit trail and lifecycle.

## Withdrawal — the operational test

Withdrawal must be as easy as giving. This means:

- A self-service mechanism — preference center, profile page, email unsubscribe link, or equivalent.
- Honored quickly — practical guidance is to apply withdrawal effective immediately and propagate downstream within minutes to hours, not days.
- No friction — no forced phone call, no requirement to log in if the consent was given without login, no upsell screens, no "are you sure" loops beyond a single confirmation.

When withdrawal is captured, the consent record's status flips, downstream systems consuming the customer's data must be notified, and any processing dependent on that consent stops. This is where the MDM event-publishing infrastructure earns its keep — a withdrawal in the customer's profile triggers events that propagate to the marketing platform, the analytics platform, the partner-integration layer.

**Withdrawal of consent is not the same as right-to-erasure.** Withdrawal stops future processing. Erasure (Article 17, one of the conditions of which is consent withdrawal) deletes the data. They can coincide but they are distinct requests. See [`right-to-erasure-in-mdm.md`](right-to-erasure-in-mdm.md).

## Consent in MDM — patterns

**Pattern A: Consent in a dedicated table outside MDM.**

```
[CMP / Consent Service] ←→ events ←→ [MDM (customer master)]
```

The consent management platform (OneTrust, Usercentrics, Ketch, internal build) is the system of record for consent. MDM holds the customer identity; consent service holds the consent state. Downstream consumers check both.

- Pro: clear separation of concerns. CMP vendors have specialized capabilities (multi-channel capture, privacy law mapping, audit reports).
- Pro: easier to swap or upgrade either system.
- Con: cross-system join needed for every operational query.
- Con: synchronization complexity.

**Pattern B: Consent as a child entity in MDM.**

The customer master includes a "consent" child entity (in Informatica Customer 360 terms, a child base object linked to the Party). Consents are first-class records inside MDM.

- Pro: single system view. Consent travels with the customer.
- Pro: MDM's stewardship, audit, and event capabilities apply to consent.
- Con: MDM isn't a specialized consent platform. Capture mechanisms (web forms, double opt-in) still need external tooling.
- Con: consent regulation evolves; MDM data model changes are expensive.

**Pattern C: Hybrid.**

CMP captures and is the authoritative store. Recent/active consent state is materialized into MDM as a child entity for operational queries. Auditable history stays in CMP.

This is what most large programs end up doing. Document the data flow and ownership boundaries.

## Consent and the lawful basis question

A subtle but important point: consent is *one* of six lawful bases. If you can rely on a different basis (contract, legitimate interests), you may not need consent at all. This is not a loophole — it's a design decision with privacy implications.

- **Contract** is the right basis for processing that's strictly necessary to deliver the service the customer signed up for. Send order confirmation emails — contract.
- **Legitimate interests** is the right basis for some operational and fraud-prevention activities, subject to a balancing test.
- **Consent** is the right basis for activities that go beyond what's strictly necessary — marketing about adjacent products, profiling for personalization, sharing with partners.

The principle: don't ask for consent for things you can do without it (the consent is meaningless if the user has no real choice), and don't assume contract or legitimate interests covers things they don't. Mis-assigning the basis is a common audit finding.

## Sources

- Regulation (EU) 2016/679, Articles 4(11), 6, 7, 9.
- gdpr.eu: consent requirements.
- usercentrics.com: *GDPR right to be forgotten* (referenced for consent-withdrawal-as-erasure-trigger).
- onetrust.com: *The GDPR Data Subject Rights*.
- ketch.com: *GDPR data subject rights*.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
