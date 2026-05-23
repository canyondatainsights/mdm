# Snowflake Data Metric Functions (DMFs)

Snowflake's native data quality monitoring is built around **Data Metric Functions** — SQL functions that compute a metric over a table or view, can be scheduled to run automatically, and can have *expectations* attached that compare the metric against thresholds.

DMFs are the right primitive for declarative data quality in Snowflake. They live in the database, scheduled by Snowflake's serverless compute, with results logged centrally. No external tool required for the basics.

Requires Snowflake Enterprise Edition or higher.

## System DMFs (built-in)

Snowflake ships system DMFs under the `SNOWFLAKE.CORE` schema. The common ones:

- `NULL_COUNT(arg_t)` — count of NULL values in a column.
- `NULL_PERCENT(arg_t)` — percentage NULL.
- `DUPLICATE_COUNT(arg_t)` — count of duplicate values.
- `UNIQUE_COUNT(arg_t)` — distinct value count.
- `ROW_COUNT(arg_t)` — total rows in the table.
- `BLANK_COUNT(arg_t)` — empty strings or whitespace-only.
- `FRESHNESS(arg_t)` — how old the most recent record is.
- `ACCEPTED_VALUES(arg_t, valid_values)` — count of values matching the supplied list.

## Associating a DMF with a table

Two-step setup:

1. **Set a schedule** on the table. This determines how often DMFs run.

```sql
ALTER TABLE silver.customer SET DATA_METRIC_SCHEDULE = '60 MINUTE';
-- or cron:
ALTER TABLE silver.customer SET DATA_METRIC_SCHEDULE = 'USING CRON 0 */6 * * * UTC';
```

2. **Associate the DMF** with specific columns:

```sql
ALTER TABLE silver.customer
  ADD DATA METRIC FUNCTION snowflake.core.null_count
  ON (email);

ALTER TABLE silver.customer
  ADD DATA METRIC FUNCTION snowflake.core.duplicate_count
  ON (email);
```

Once associated and scheduled, Snowflake runs the DMFs automatically. Results land in the account's event table.

## Calling a DMF directly (testing)

Before associating in production, test the DMF against a sample:

```sql
SELECT snowflake.core.null_count(
  SELECT email FROM silver.customer
);
```

This is also useful for ad-hoc data exploration.

## Custom DMFs

When system DMFs don't cover the rule, write your own:

```sql
CREATE OR REPLACE DATA METRIC FUNCTION
  governance.dmfs.invalid_email_count(
    arg_t TABLE(email STRING)
  )
RETURNS NUMBER
AS
$$
  SELECT COUNT(*)
  FROM arg_t
  WHERE email IS NOT NULL
    AND NOT REGEXP_LIKE(email, '^[A-Za-z0-9._%+\\-]+@[A-Za-z0-9.\\-]+\\.[A-Za-z]{2,}$')
$$;

-- Then associate it:
ALTER TABLE silver.customer
  ADD DATA METRIC FUNCTION governance.dmfs.invalid_email_count
  ON (email);
```

Custom DMFs are just SQL functions. The signature must take a TABLE argument with the columns being measured.

## Multi-column DMFs

DMFs can take multiple columns:

```sql
CREATE OR REPLACE DATA METRIC FUNCTION
  governance.dmfs.country_state_mismatch_count(
    arg_t TABLE(country STRING, state STRING)
  )
RETURNS NUMBER
AS
$$
  SELECT COUNT(*)
  FROM arg_t
  WHERE country = 'US'
    AND state NOT IN (SELECT code FROM reference.us_states)
$$;

ALTER TABLE silver.customer
  ADD DATA METRIC FUNCTION governance.dmfs.country_state_mismatch_count
  ON (country, state);
```

This is how you express *consistency* rules — DAMA's third DQ dimension. Cross-column logic is where DMFs really earn their keep.

## Cross-table DMFs (referential integrity)

DMFs can join across tables. The first table argument is the table the DMF is associated with; additional arguments take subqueries.

```sql
CREATE OR REPLACE DATA METRIC FUNCTION
  governance.dmfs.orphan_orders(
    orders_t TABLE(customer_id STRING),
    valid_customers TABLE(customer_id STRING)
  )
RETURNS NUMBER
AS
$$
  SELECT COUNT(*)
  FROM orders_t
  WHERE customer_id NOT IN (SELECT customer_id FROM valid_customers)
$$;

ALTER TABLE silver.orders
  ADD DATA METRIC FUNCTION governance.dmfs.orphan_orders
  ON (customer_id, (SELECT customer_id FROM silver.customer));
```

This catches referential integrity violations — orders that reference customers that don't exist in silver.

## Expectations

A DMF on its own produces a number. To make it a *check*, attach an expectation — a threshold that the number must satisfy.

```sql
ALTER TABLE silver.customer
  MODIFY DATA METRIC FUNCTION snowflake.core.null_count
  ON (email)
  SET EXPECTATION (value < 100);
```

The expectation defines pass/fail. When a DMF evaluates and the expectation is violated, the result is logged as a quality incident. Downstream, you can subscribe to alerts on these incidents.

## Viewing results

DMF results are logged. The metadata views to query:

- `SNOWFLAKE.LOCAL.DATA_QUALITY_MONITORING_RESULTS` — recent DMF results.
- `SNOWFLAKE.LOCAL.DATA_QUALITY_MONITORING_USAGE_HISTORY` — credit consumption for DMF execution.
- Snowsight's Data Quality dashboard provides a UI over these.

For a custom alerting layer, query the results view from a task and trigger external notification:

```sql
CREATE TASK alert_on_dq_failure
  WAREHOUSE = ops_wh
  SCHEDULE = 'USING CRON 0 * * * * UTC'
AS
INSERT INTO ops.dq_alerts
SELECT *
FROM SNOWFLAKE.LOCAL.DATA_QUALITY_MONITORING_RESULTS
WHERE measurement_time > DATEADD(hour, -1, CURRENT_TIMESTAMP())
  AND value > (SELECT threshold FROM ops.dq_thresholds WHERE metric_name = ...);
```

Combined with Snowflake's external functions or notification integrations, this drives Slack / PagerDuty / etc.

## DMFs vs DLT expectations (the comparison)

For teams running both Databricks and Snowflake, the question comes up.

| Aspect | Snowflake DMF | Databricks DLT Expectation |
|---|---|---|
| Granularity | Table-level metric | Per-record evaluation |
| Mode | Run on schedule, report violations | Run in pipeline, can drop/fail records |
| Best for | Monitoring trends, post-load auditing | Gating pipeline progression |
| Storage of results | Native event table | Native event log; queryable |
| Visibility | Snowsight DQ dashboard | DLT pipeline UI |
| Custom logic | Custom DMF in SQL | Python or SQL constraint |

These aren't competitors; they're answering different questions. **Use DMFs for ongoing monitoring of established tables. Use DLT expectations to gate progression through pipeline layers.** A real-world deployment might have both — DLT expectations in the silver-build pipeline, DMFs scheduled on the silver and gold tables for ongoing watch.

## Anomaly detection

Snowflake also offers anomaly detection over DMF history. The platform learns the baseline behavior of a metric and flags deviations. Useful for "row count dropped 80% from yesterday" without you having to write the threshold rule.

```sql
ALTER TABLE silver.customer
  ADD DATA METRIC FUNCTION snowflake.core.row_count
  ON ();

-- Anomaly detection on the row_count metric
-- Configured in Snowsight or via specific ML functions
```

This is newer functionality and worth refreshing the docs on when you implement.

## Practical patterns

**Pattern: DMFs on silver, MDM consumes only if DMFs pass.** Schedule DMFs on silver to run before the MDM landing materialization. If critical DMFs failed in the last run, skip the materialization (encode this as a check in the orchestrator). Avoids polluting MDM with known-bad silver data.

**Pattern: DMFs on gold, with anomaly detection.** Gold tables are the consumer-facing artifacts; anomalies here are what hits the dashboard or the customer. Watch them tightly.

**Pattern: A `dq_thresholds` reference table.** Threshold values per DMF stored externally, not hardcoded in expectations. Changing a threshold means updating a row, not deploying a new DDL.

## Sources

- docs.snowflake.com — *Introduction to data quality checks*, *Custom data metric functions*, *Use SQL to set up data metric functions*, *DATA_METRIC_FUNCTION_EXPECTATIONS*.
- Snowflake quickstart: *Getting Started with Data Quality in Snowflake*.
- Cittabase: *Enhancing Data Pipelines in Snowflake with Data Quality Checks Using DMFs*.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
