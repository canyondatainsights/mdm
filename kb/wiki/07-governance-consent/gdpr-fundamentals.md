# GDPR — Fundamentals

The General Data Protection Regulation (EU Regulation 2016/679) has been in force since 25 May 2018. It applies to processing of personal data of individuals in the EU/EEA, regardless of where the processor is located. Penalties are real (up to 4% of global annual turnover or €20m, whichever is higher), enforcement is active, and most major jurisdictions outside the EU have adopted GDPR-shaped laws (UK GDPR, LGPD in Brazil, the various US state laws, India's DPDP). Understanding GDPR isn't optional for anyone running an enterprise MDM program.

This page is the architect's view of the parts that matter for MDM. It is not legal advice. Consult your DPO and legal counsel for actual compliance decisions.

## Key definitions

**Personal data.** Any information relating to an identified or identifiable natural person. Direct identifiers (name, email, government ID), indirect identifiers that resolve to a person in combination (postal code + age + employer), and online identifiers (IP, cookie ID, device ID). In MDM, almost everything in a customer master is personal data.

**Special categories of personal data.** Higher-protection categories: racial or ethnic origin, political opinions, religious beliefs, trade union membership, genetic data, biometric data for unique identification, health data, sex life or sexual orientation. Processing these requires a stronger lawful basis (typically explicit consent or specific legal authorization).

**Processing.** Almost anything you do with personal data — collection, recording, organization, storage, retrieval, consultation, use, disclosure, alignment, erasure, destruction. Reading a record from MDM is processing.

**Controller.** The entity that determines the purposes and means of processing. Your company, in most cases, with respect to its customer data.

**Processor.** An entity that processes personal data on behalf of a controller. Your cloud provider, your MDM SaaS vendor, your CRM vendor — each is a processor for the data you put in their systems.

**Joint controller.** Two or more controllers jointly determining purposes and means. Less common; arises in marketplaces, shared ventures.

**Data subject.** The natural person to whom personal data relates.

## The seven principles (Article 5)

These are the foundation. Every processing activity must respect all seven.

1. **Lawfulness, fairness, transparency.** Processing must have a lawful basis (see next section), be fair to the data subject, and be transparent — the subject must know what's being processed and why.
2. **Purpose limitation.** Personal data must be collected for specified, explicit, legitimate purposes and not further processed in a manner incompatible with those purposes.
3. **Data minimization.** Adequate, relevant, and limited to what is necessary. Don't collect data you don't need.
4. **Accuracy.** Personal data must be accurate and, where necessary, kept up to date. Inaccurate data must be erased or rectified without delay.
5. **Storage limitation.** Kept in identifiable form for no longer than necessary for the purposes.
6. **Integrity and confidentiality.** Processed with appropriate security — protection against unauthorized processing, accidental loss, destruction, damage.
7. **Accountability.** The controller is responsible for demonstrating compliance with these principles.

Note the implications for MDM:

- **Accuracy** maps directly to data quality. The DQ work documented elsewhere in this wiki is partly a GDPR compliance activity.
- **Storage limitation** means retention policies are not optional. MDM must implement them.
- **Accountability** means audit trails are not optional. XREF, history, change logs serve this.
- **Purpose limitation** means MDM cannot become a generic data lake — data is collected for stated purposes, used for those purposes, and re-used only under defined conditions.

## Lawful bases for processing (Article 6)

Processing personal data requires at least one of six lawful bases. You pick one per processing activity. You don't get to mix-and-match retroactively.

1. **Consent.** The data subject has given consent to processing for one or more specific purposes. Must be freely given, specific, informed, unambiguous, and withdrawable. See [`consent-management.md`](consent-management.md).
2. **Contract.** Processing is necessary for performance of a contract with the data subject (or pre-contractual steps requested by them). The most common basis for B2C operational processing.
3. **Legal obligation.** Processing is necessary to comply with a legal obligation. Tax records, KYC, AML, regulatory reporting.
4. **Vital interests.** Processing is necessary to protect the vital interests of the data subject or another person. Emergency medical situations; rarely the basis for routine MDM processing.
5. **Public task.** Processing is necessary for the performance of a task carried out in the public interest or in the exercise of official authority. Government and public-sector use.
6. **Legitimate interests.** Processing is necessary for the legitimate interests pursued by the controller or a third party, except where overridden by the rights of the data subject. Subject to a balancing test (legitimate interest assessment, LIA). Common basis for fraud prevention, security, intra-group administrative purposes.

For special-category data, Article 9 provides a separate, narrower set of lawful bases — typically explicit consent or specific legal authorization.

**Choosing a basis is consequential.** Different bases come with different data subject rights. The right to erasure under Article 17 is stronger when the basis is consent (withdrawal triggers erasure) than when the basis is contract (you need the data to perform the contract). Document the chosen basis for each processing activity in your Record of Processing Activities (ROPA, Article 30).

## Data subject rights

GDPR grants data subjects eight enumerated rights, plus the right to withdraw consent.

1. **Right to be informed (Articles 12-14).** Subject must be informed about collection and use of their data. Privacy notices.
2. **Right of access (Article 15).** Subject can request a copy of their personal data and information about how it's processed. This is the DSAR (Data Subject Access Request).
3. **Right to rectification (Article 16).** Subject can request correction of inaccurate data. Maps to stewardship workflows in MDM.
4. **Right to erasure / right to be forgotten (Article 17).** Subject can request deletion under specific conditions. See [`right-to-erasure-in-mdm.md`](right-to-erasure-in-mdm.md) — the most operationally complex right for MDM.
5. **Right to restriction of processing (Article 18).** Subject can request the data be retained but not actively processed. Less common; relevant during disputes.
6. **Right to data portability (Article 20).** Subject can receive their data in a structured, commonly-used, machine-readable format and have it transmitted to another controller. Applies when basis is consent or contract.
7. **Right to object (Article 21).** Subject can object to processing based on legitimate interests or public task; for direct marketing, the objection is absolute.
8. **Rights related to automated decision-making (Article 22).** Subject has rights with respect to decisions based solely on automated processing — including profiling — that produce legal or similarly significant effects. Right to human review, to express their view, to contest the decision.

Plus:

- **Right to withdraw consent (Article 7).** When consent is the lawful basis, the subject can withdraw it at any time, as easily as they gave it. Withdrawal doesn't affect lawfulness of processing before withdrawal but stops processing going forward.

The controller has **one month** from request receipt to fulfill (or refuse, with reasons). This can be extended by two months for complex cases. The clock starts when the request is received; not when the legal team gets around to looking at it.

## Records of Processing Activities (Article 30)

Controllers (and most processors) must maintain a written record of processing activities. The record includes:

- Controller and DPO contact details.
- Purposes of processing.
- Categories of data subjects and personal data.
- Recipients (including in third countries).
- International transfers and the safeguards used.
- Retention periods.
- Description of technical and organizational security measures.

For MDM, this typically means: a row in the ROPA for "customer master data management" with the above filled out. The ROPA isn't user-facing; it's evidence for a supervisory authority audit.

## Data Protection Impact Assessments (Article 35)

A DPIA is required for processing likely to result in high risk to data subjects. MDM programs typically warrant a DPIA when first stood up — large-scale processing of personal data, including special-category data, potentially with profiling. The DPIA documents:

- The processing operation and purposes.
- An assessment of necessity and proportionality.
- An assessment of risks to data subjects.
- Measures to address those risks.

DPIAs are revisited when processing changes materially.

## International transfers

Personal data of EU/EEA subjects can be transferred outside the EEA only under specific conditions: adequacy decisions, Standard Contractual Clauses (SCCs), Binding Corporate Rules (BCRs), or specific derogations. The landscape changed significantly with Schrems II (2020) and the EU-US Data Privacy Framework (2023). If your MDM is in a US cloud region serving EU customers, your transfer mechanism must be documented and current.

This is a fast-moving area legally. Verify current state when designing.

## Breach notification (Articles 33-34)

Personal data breach? The controller has **72 hours** to notify the supervisory authority unless the breach is unlikely to result in a risk to data subjects. If the breach is high-risk, data subjects must also be notified, without undue delay.

For MDM specifically, the "is this a breach" question often surfaces around: unauthorized access logs, mis-shipped data extracts, configuration errors exposing data, vendor incidents in your processor chain. Have an incident response process that includes the privacy team.

## Practical implications for MDM design

- **Mark personal data in the schema.** Every column that holds personal data should be tagged (in Unity Catalog, Snowflake tags, Informatica Axon, Collibra — whatever your catalog is). Special category data tagged separately.
- **Log access.** Who queried what, when. Required to investigate suspected breaches; useful for general audit.
- **Implement retention.** A data record that's outlived its purpose should be deleted or anonymized. MDM history and XREF tables have their own retention questions; address them explicitly.
- **Map consent to records.** Where consent is the basis, the consent state is part of the data model. See [`consent-management.md`](consent-management.md).
- **Plan for the rights workflow.** DSAR, erasure, rectification — each has a workflow that must run within deadlines. See [`right-to-erasure-in-mdm.md`](right-to-erasure-in-mdm.md).

## Sources

- Regulation (EU) 2016/679 — full text on EUR-Lex.
- gdpr.eu — practitioner-friendly summaries.
- onetrust.com: *The GDPR Data Subject Rights*.
- ketch.com: *GDPR data subject rights*.
- truevault.com: *What are the rights of data subjects under GDPR*.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
