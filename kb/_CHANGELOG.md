# Knowledge Base Changelog

Every meaningful change to the wiki gets a line here. Format: date, scope, summary, source. This is the lineage record.

## 2026-05-23 — v1.0 Initial build

- Created folder structure: `raw/`, `wiki/` (9 sections), `output/`.
- Created `README.md`, `_INDEX.md`, `_CHANGELOG.md`, `MANIFEST.md`, `export-kb.sh`.
- Wrote 30 wiki pages across all nine sections:
  - 01-foundations: mdm-concepts, glossary
  - 02-informatica-mdm: product-landscape, data-model, customer-360, supplier-360, product-360, reference-360, match-merge-survivorship
  - 03-data-quality: idq-overview, profiling-and-scorecards, cleansing-standardization, address-verification, dq-rule-design
  - 04-pipelines-medallion: medallion-overview, staging-for-mdm, pipeline-design-patterns
  - 05-snowflake: snowflake-for-mdm-staging, data-metric-functions, sql-cleansing-patterns
  - 06-databricks: databricks-for-mdm-staging, dlt-expectations, pyspark-cleansing-patterns
  - 07-governance-consent: data-governance-overview, gdpr-fundamentals, consent-management, right-to-erasure-in-mdm, ccpa-and-other-laws
  - 08-patterns-playbooks: end-to-end-reference-architecture, source-onboarding-checklist, match-rule-tuning-playbook
  - 09-decisions-adrs: _template (with worked example)
- Sources: Informatica documentation (docs.informatica.com) for MDM 10.4/10.5 and Customer/Supplier/Product/Reference 360; Informatica Tech Tuesdays material for match/merge and DQ best practices; Databricks documentation for medallion architecture and Delta Live Tables expectations; Snowflake documentation for Data Metric Functions and data quality monitoring; GDPR.eu, OneTrust, Usercentrics, Ketch, and EU regulation text for governance/consent pages.
- Voice: senior technical data architect. Opinionated where the field has a defensible best answer; explicit about trade-offs where it doesn't.
- Portability: established via plain UTF-8 Markdown, MANIFEST.md operating contract, and export-kb.sh archive script.

---

## How to add a changelog entry

When updating the wiki, add an entry under a dated heading at the top of this file:

```
## YYYY-MM-DD — short title

- [page-path] — what changed and why
- Source: [URL, uploaded file, or "Q&A with user"]
```

Keep entries terse. The page's own *Revision log* section carries the detail; this file is the index of changes across the whole KB.
