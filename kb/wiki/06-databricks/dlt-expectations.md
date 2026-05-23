# DLT Expectations

Delta Live Tables (DLT) is Databricks' declarative ETL framework. You define tables and their dependencies; DLT manages execution order, lineage, retries, and quality monitoring. **Expectations** are the data quality primitive — rules attached to table definitions that evaluate every record.

This page is the working reference for DLT expectations in the context of MDM-feeding pipelines.

## The three expectation modes

DLT supports three flavors of expectation, distinguished by what happens when a record violates the rule.

### `expect` — track and warn

```python
@dlt.table
@dlt.expect("valid_email", "email IS NOT NULL AND email RLIKE '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$'")
def silver_customer():
  return spark.readStream.table("bronze.customer_raw")
```

Records violating the expectation are *retained in the output*. The violation is logged. Use this when you want visibility but don't want to lose data.

Right for: monitoring trends, surfacing quality issues to stewards, gradually tightening rules without breaking the pipeline.

### `expect_or_drop` — track and drop

```python
@dlt.table
@dlt.expect_or_drop("not_null_email", "email IS NOT NULL")
def silver_customer():
  return spark.readStream.table("bronze.customer_raw")
```

Records violating the expectation are *dropped from the output*. The violation is logged. The pipeline continues.

Right for: silver-layer hygiene — records without primary keys or other unrecoverable issues shouldn't propagate. Critical: when you use this, ensure you ALSO have a quarantine path so the dropped records aren't gone forever. DLT itself doesn't write quarantine — you need to either configure a separate quarantine table or accept the loss.

### `expect_or_fail` — halt the pipeline

```python
@dlt.table
@dlt.expect_or_fail("not_negative_amount", "amount >= 0")
def silver_orders():
  return spark.readStream.table("bronze.orders_raw")
```

A single violating record *halts the entire pipeline* with an exception. Run aborts.

Right for: catastrophic violations — fundamental schema invariants, business-critical rules where a single violation indicates a serious problem and you'd rather stop processing than continue with bad data. Use sparingly. A pipeline that halts on every minor issue becomes noise and gets disabled.

## Choosing among the three

The pattern that works in practice:

| Rule severity | Use |
|---|---|
| "Surface as a metric; data still useful" | `expect` |
| "This record is unusable; drop it" | `expect_or_drop` (with quarantine) |
| "This must never happen; if it does we stop" | `expect_or_fail` (sparingly) |

A pipeline typically has 20+ `expect`, a handful of `expect_or_drop`, and maybe 1–2 `expect_or_fail` total.

## Multiple expectations

You can attach many expectations to one table:

```python
@dlt.table
@dlt.expect("valid_email", "email RLIKE '^.+@.+\\..+$'")
@dlt.expect("valid_phone", "phone RLIKE '^\\+?[0-9]{10,15}$'")
@dlt.expect_or_drop("has_source_id", "source_id IS NOT NULL")
@dlt.expect_or_drop("has_source_system", "source_system IS NOT NULL")
def silver_customer():
  return ...
```

Each violation is tracked separately. The metrics dashboard shows pass/fail per expectation.

## Combining expectations

Two helpers for groups of related rules:

`@dlt.expect_all({...})` — multiple expectations in one decorator (warning-only):

```python
@dlt.expect_all({
  "valid_email": "email RLIKE '^.+@.+\\..+$'",
  "valid_phone": "phone IS NULL OR phone RLIKE '^\\+?[0-9]{10,15}$'",
  "name_present": "first_name IS NOT NULL OR last_name IS NOT NULL"
})
```

`@dlt.expect_all_or_drop({...})` — same, but drop on any violation.

`@dlt.expect_all_or_fail({...})` — same, but fail on any violation.

Useful for terse declarations when many simple rules apply.

## Quarantine pattern with DLT

DLT's `_or_drop` discards records. If you need quarantine instead:

```python
import dlt
from pyspark.sql.functions import expr, current_timestamp

# Expectation as a column for routing
@dlt.table(name="silver_customer_routed")
def routed():
  return (
    spark.readStream.table("bronze.customer_raw")
      .withColumn("is_valid_email", expr("email RLIKE '^.+@.+\\..+$'"))
      .withColumn("is_valid_phone", expr("phone IS NULL OR phone RLIKE '^\\+?[0-9]{10,15}$'"))
  )

# Good records: silver
@dlt.table(name="silver_customer")
def silver_customer():
  return dlt.read_stream("silver_customer_routed").filter("is_valid_email AND is_valid_phone")

# Bad records: quarantine
@dlt.table(name="silver_customer_quarantine")
def quarantine():
  return (
    dlt.read_stream("silver_customer_routed")
      .filter("NOT (is_valid_email AND is_valid_phone)")
      .withColumn("quarantined_at", current_timestamp())
  )
```

This pattern preserves the audit trail. Stewards work the quarantine; corrections re-enter via bronze re-ingest.

## Monitoring expectation results

DLT logs every expectation evaluation to the pipeline's event log. You can query it:

```sql
SELECT
  details:flow_definition.expectations.name AS expectation,
  details:flow_definition.expectations.passed_records AS passed,
  details:flow_definition.expectations.failed_records AS failed
FROM event_log(pipeline_id => 'your-pipeline-id')
WHERE event_type = 'flow_progress'
  AND timestamp > current_timestamp() - INTERVAL 1 DAY;
```

DLT's UI also surfaces a quality dashboard per pipeline. Worth pointing stewards at directly.

## Common patterns for MDM-feeding pipelines

**Pattern: hard validation on identifier completeness, soft on enrichment.**

```python
@dlt.table
@dlt.expect_or_drop("has_keys", "source_system IS NOT NULL AND source_id IS NOT NULL")
@dlt.expect("has_email", "email IS NOT NULL")
@dlt.expect("has_phone", "phone IS NOT NULL")
@dlt.expect("has_address", "address_line_1 IS NOT NULL")
def silver_customer():
  return ...
```

Records without keys can't reach MDM (drop). Records missing enrichment columns flow through with metrics (warn).

**Pattern: cross-column consistency.**

```python
@dlt.expect("state_consistent_with_country",
            "(country != 'US') OR (state IN ('AL','AK','AZ', ..., 'WY'))")
```

Same DQ concept as the Snowflake DMF cross-column pattern.

**Pattern: reference data check.**

```python
@dlt.expect("country_in_reference",
            "country IN (SELECT code FROM reference.country WHERE active = true)")
```

DLT can reference other tables in expectations; the lookup is evaluated per row.

## DLT vs Snowflake DMFs

Covered in [`05-snowflake/data-metric-functions.md`](../05-snowflake/data-metric-functions.md). Short version: DLT expectations gate pipeline progression record-by-record; DMFs monitor table-level metrics on schedule. They answer different questions and many shops use both.

## What to avoid

- **Over-using `expect_or_fail`.** Becomes pipeline whack-a-mole.
- **Hidden silent drops.** Every `expect_or_drop` needs a documented quarantine path or an explicit acceptance that those records are gone.
- **Expectations that pass 100% of the time.** They're documentation, not validation. Either tighten them or remove them.
- **Expectations that never get reviewed.** Quality dashboards that no steward looks at are technical debt.

## Sources

- docs.databricks.com — *Manage data quality with pipeline expectations*.
- DataCamp: *Managing Data Quality with Databricks Delta Live Tables*.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
