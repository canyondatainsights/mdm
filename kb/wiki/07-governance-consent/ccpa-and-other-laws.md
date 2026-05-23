# CCPA, CPRA, and Other Privacy Laws

GDPR set the template; most major jurisdictions now have a GDPR-shaped privacy law. The deltas matter for MDM design because a global program has to comply with the strictest applicable regime per subject — which means knowing which regime applies.

This page is a working summary of the most-encountered laws beyond GDPR. Not legal advice. Consult counsel for actual compliance decisions in any specific case.

## United States — CCPA and CPRA

**California Consumer Privacy Act (CCPA)**, in force 2020, expanded by the **California Privacy Rights Act (CPRA)** in force 2023. Applies to for-profit businesses doing business in California that meet certain thresholds (revenue, volume of consumer data, percentage of revenue from selling/sharing data).

Key consumer rights:

- Right to know what personal information is collected and how it's used.
- Right to delete personal information.
- Right to opt out of "sale" or "sharing" of personal information.
- Right to correct inaccurate personal information.
- Right to limit use and disclosure of "sensitive personal information" (a separate category, similar to GDPR's special categories but defined differently — includes precise geolocation, biometrics, etc.).
- Right to non-discrimination for exercising rights.
- Right to data portability.

Practical differences from GDPR:

- **Opt-out, not opt-in.** CCPA's default is that processing is allowed; consumers opt out of specific uses. GDPR's default is that processing requires a lawful basis, which is often consent. This is the single biggest design difference.
- **No equivalent of "lawful basis" framework.** CCPA doesn't require a specific basis for processing; it requires notice and opt-out rights.
- **Definition of "sale" is broad.** Sharing data with third parties for value (broadly defined) counts. The "Do Not Sell or Share My Personal Information" link is mandatory.
- **Verification burden.** CCPA requires verifying the consumer's identity before fulfilling rights requests, with standards for verification proportionate to the sensitivity of the request.
- **Timeline.** 45 days (extendable by 45 more) for response, vs GDPR's 30 days (extendable by 60 more).

For MDM design: if you operate in California, the MDM must support opt-out tracking (especially for "sale" / "sharing" purposes) and identity-verified rights requests. The data subject rights workflow is largely the same as for GDPR; the trigger conditions and verification standards differ.

## Other US state laws

The US has no federal privacy law. State laws have proliferated. As of mid-2026, the GDPR/CCPA-shaped laws in force or coming into force in the US include:

- Virginia (VCDPA), Colorado (CPA), Connecticut (CTDPA), Utah (UCPA), Iowa, Indiana, Tennessee, Texas (TDPSA), Florida (FDBR), Oregon, Montana, Delaware, New Hampshire, New Jersey, Maryland, Minnesota, Rhode Island.

Each has its own thresholds, rights, definitions, and timelines. Common patterns:

- Opt-out for targeted advertising, sale of personal data, and profiling with significant effects.
- Consumer rights: access, correction, deletion, portability.
- Sensitive data with stricter rules (Colorado, Connecticut treat sensitive data opt-in; some others opt-out).
- Universal opt-out signals (e.g., Global Privacy Control) recognized in several states.

The practical advice for MDM in the US: design to the most stringent state's rules as the baseline (typically California), and consult counsel for state-by-state nuance.

## UK GDPR

After Brexit, the UK adopted the GDPR text into domestic law as "UK GDPR". Substantive provisions are largely identical to EU GDPR. Key differences:

- The supervisory authority is the ICO (Information Commissioner's Office), not an EU body.
- Transfers between UK and EU require their own assessment (the adequacy decision currently in place could change).
- UK-specific implementation details under the Data Protection Act 2018.

For most MDM design purposes, treating UK and EU subjects under the same compliance program is the operational simplification. The legal mechanism for data transfers between them needs documentation.

## Brazil — LGPD

**Lei Geral de Proteção de Dados** (LGPD), in force 2020. Modeled closely on GDPR.

- Six categories of data subject rights, very similar to GDPR.
- Ten lawful bases for processing (more than GDPR's six; includes some Brazil-specific bases like credit protection).
- The supervisory authority is the ANPD (Autoridade Nacional de Proteção de Dados).
- Fines up to 2% of revenue in Brazil (capped at R$50m per infraction), lower than GDPR's percentages.

For MDM purposes, LGPD is operationally GDPR-shaped. Subjects' rights, notice requirements, lawful-basis discipline, and breach notification are recognizable.

## India — DPDP Act 2023

**Digital Personal Data Protection Act** passed 2023, with phased implementation. Key features:

- Consent-centric model — consent is the primary lawful basis (with some "legitimate uses" defined separately).
- Notice and consent requirements emphasized.
- Data Principal (DPDP's term for data subject) rights: access, correction, erasure, grievance redressal, nominate someone to exercise rights after death.
- Data Fiduciary (DPDP's term for controller) obligations: data minimization, accuracy, security, breach notification.
- Significant Data Fiduciaries — a higher-obligation category designated by the government, with DPO requirements similar to GDPR.
- Children's data has stricter protections (under 18, with parental consent required).
- Cross-border transfer rules to be specified by government notification.

For MDM with Indian subjects, the consent-centric design is the biggest difference from GDPR (which allows multiple bases). Specific requirements depend on whether your organization is designated a Significant Data Fiduciary.

## Other regimes worth knowing

- **PIPEDA (Canada)** — federal law, with provincial laws (PIPA in Alberta, Quebec Law 25). Quebec's Law 25 is notably stricter than the federal baseline.
- **PDPA (Singapore)** — consent-based, with do-not-call register implications.
- **PDPA (Thailand)** — broadly GDPR-shaped.
- **POPIA (South Africa)** — Protection of Personal Information Act.
- **APPI (Japan)** — Act on the Protection of Personal Information, with cross-border transfer rules.
- **PIPL (China)** — Personal Information Protection Law. Stringent. Data localization implications for foreign companies.

## How to design MDM for a multi-regime world

A few patterns:

**Identify the applicable regime per subject.** Each subject has a "jurisdiction" attribute on the MDM record — typically inferred from residence country, with explicit override possible. The applicable regime drives consent requirements, rights workflows, retention rules.

**Design to the strictest applicable regime as default.** GDPR-shaped operational discipline (consent, lawful basis, rights workflows, breach notification) usually satisfies most other regimes by construction. Stricter regimes (PIPL data localization, certain sensitive-data restrictions) need additional measures.

**Federate where regulation requires.** Some regimes (notably PIPL, certain implementations of LGPD, some interpretations of GDPR) effectively require local processing. A globally centralized MDM may not be permissible for some subject populations. Federated MDM — regional hubs reconciled at the metadata level — is the architectural answer.

**Document everything.** The Records of Processing Activities (or equivalent) needs to cover every regime, every processing activity. This is the artifact that wins audits.

**Stay current.** The legal landscape changes constantly. This page reflects mid-2026; verify before relying on specifics.

## Sources

- iapp.org — global privacy law trackers.
- onetrust.com — global privacy regulation summaries.
- Various jurisdiction-specific regulatory sites; verify current text when designing.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
