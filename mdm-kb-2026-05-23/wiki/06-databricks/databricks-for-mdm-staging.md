# Databricks for MDM Staging

Databricks is the other strong choice for the medallion layers feeding Informatica MDM. Where Snowflake is SQL-first and serverless-feeling, Databricks is Spark/Python-first with deeper notebook-style development. Either works; the choice usually comes down to existing skills and broader analytics strategy more than MDM fit.

This page covers Databricks-specific patterns for bronze/silver pipelines feeding MDM, and gold pipelines consuming the BVT back.

## Architecture sketch

```
Source files / streams
   │
   ▼
[Cloud object storage]              ← S3 / ADLS / GCS
   │
   ▼  (Auto Loader)
[bronze Delta tables]               ← raw, append-only, schema-evolution-friendly
   │
   ▼  (DLT or notebook job)
[silver Delta tables]               ← cleansed, validated, conformed
   │
   ▼  (CDI mapping, or direct read by MDM via Unity Catalog connector)
[Informatica MDM landing/staging/base-object]
   │
   ▼  (BVT materialization)
[gold Delta tables]                 ← consumer-shaped
   │
   ▼
[BI tools, downstream apps, ML feature store, reverse-ETL]
```

## Unity Catalog

The starting assumption for any new Databricks deployment is Unity Catalog. It is Databricks' unified governance layer covering:

- **Catalogs, schemas, tables, volumes** — three-level namespace (`catalog.schema.table`).
- **Fine-grained access control** — table, column, and row-level grants. Column masks and row filters for dynamic policy.
- **Lineage** — table-to-table and column-to-column, automatically captured from query history.
- **Data discovery** — searchable metadata, tagging, business glossary integration.
- **Audit** — every query, every access, logged.

For MDM purposes, Unity Catalog matters because **it's how you implement column-level governance over PII before MDM consumes the silver layer**. A silver-layer customer table contains email, phone, full address — these are PII. UC dynamic views and column masks let you control who sees what without duplicating the table.

The naming pattern most teams settle on:

```
mdm_dev.bronze.customer_raw      -- dev environment, bronze schema
mdm_dev.silver.customer
mdm_dev.gold.customer_master

mdm_prd.bronze.customer_raw      -- production
mdm_prd.silver.customer
mdm_prd.gold.customer_master
```

Or environment-prefixed catalogs (`dev_mdm`, `prd_mdm`) with bronze/silver/gold as schemas. The choice depends on org-wide conventions.

## Auto Loader

Auto Loader is Databricks' ingestion primitive for files arriving in cloud storage. It handles:

- **Incremental file discovery** — knows which files are new since the last run. Uses cloud-native file notification (S3 Event Notifications, Azure Event Grid) for low latency, or directory listing for simpler setups.
- **Schema inference** — infers the schema on first run.
- **Schema evolution** — handles new columns appearing in source files without crashing. Modes: `addNewColumns`, `failOnNewColumns`, `rescue` (capture unknown columns into a `_rescued_data` JSON column), `none`.
- **Type rescue** — if a column's type changes (string where number expected), rescue the value into `_rescued_data` instead of failing the row.

Practical pattern for bronze ingest:

```python
from pyspark.sql.functions import current_timestamp, input_file_name

df = (
  spark.readStream
    .format("cloudFiles")
    .option("cloudFiles.format", "json")
    .option("cloudFiles.schemaLocation", "/mnt/schemas/customer_raw")
    .option("cloudFiles.schemaEvolutionMode", "addNewColumns")
    .load("s3://acme-data/sources/crm/customers/")
)

(df
  .withColumn("ingest_ts", current_timestamp())
  .withColumn("source_file", input_file_name())
  .writeStream
    .format("delta")
    .option("checkpointLocation", "/mnt/checkpoints/customer_raw")
    .trigger(availableNow=True)
    .toTable("mdm_prd.bronze.customer_raw")
)
```

The `trigger(availableNow=True)` is the pragmatic choice for most MDM use cases — it runs the stream until all available files are processed, then stops. You don't pay for idle streaming compute. Schedule the job to run hourly or on file arrival.

## Delta Lake fundamentals worth knowing

Delta is the storage layer underneath. Useful properties for MDM-feeding pipelines:

- **ACID transactions.** A MERGE either fully succeeds or fully fails. No half-applied updates.
- **Time travel.** Query the table as of a previous version: `SELECT * FROM silver.customer VERSION AS OF 23` or `TIMESTAMP AS OF '2026-05-20 00:00:00'`. Useful for audit, debugging, replay.
- **Schema enforcement.** Writes that don't match the schema fail by default. Schema evolution is opt-in (`mergeSchema = true`).
- **MERGE INTO.** Upsert semantics. Critical for incremental silver loads:

```sql
MERGE INTO silver.customer t
USING bronze.customer_raw_changes s
  ON t.source_system = s.source_system AND t.source_id = s.source_id
WHEN MATCHED AND s.extract_ts > t.extract_ts THEN UPDATE SET *
WHEN NOT MATCHED THEN INSERT *;
```

- **Change Data Feed (CDF).** A Delta table can emit its own change log. `SELECT * FROM table_changes('silver.customer', start_version, end_version)` returns inserts, updates (before and after), and deletes. The Delta equivalent of Snowflake Streams.
- **OPTIMIZE and VACUUM.** Periodic maintenance. OPTIMIZE compacts small files; VACUUM cleans up old versions past the retention threshold. Schedule these.

## Materializing to MDM landing

Same three patterns as Snowflake (see [`05-snowflake/snowflake-for-mdm-staging.md`](../05-snowflake/snowflake-for-mdm-staging.md)):

**Informatica CDI mapping** — most common. CDI reads from Databricks (via JDBC/Databricks connector), writes to MDM landing. Scheduled or event-triggered.

**Direct read by MDM** — MDM points at a Databricks table via Unity Catalog's Delta sharing or external table. Less common for MDM SaaS; possible for on-prem MDM with the right connector.

**File drop** — Databricks writes a Parquet/CSV extract to cloud storage; MDM ingests from there.

For new MDM SaaS programs, CDI is the supported path.

## Receiving BVT back

After MDM resolves the golden record, materialize back into Databricks gold:

```python
bvt_df = spark.read.format("informatica").load(...)   # or via CDI extract

(bvt_df
  .write
  .format("delta")
  .mode("overwrite")
  .option("overwriteSchema", "true")
  .saveAsTable("mdm_prd.gold.customer_master"))
```

In practice, you almost always do MERGE rather than overwrite, since the BVT extract is incremental. And the gold table is shaped for consumers — not a verbatim BVT copy.

## Workflow orchestration

Databricks Workflows (the native scheduler) handles job orchestration. Multi-task jobs can express:

- Bronze ingest → silver build → DQ check → MDM extract → gold materialize → downstream notifications.
- Conditional execution (skip MDM extract if DQ check failed).
- Retry and notification policy per task.

External orchestrators (Airflow, Dagster, Prefect) also work. The choice depends on whether you have an enterprise orchestration standard.

## Cost notes

- **Job clusters, not all-purpose clusters, for production pipelines.** Job clusters are ephemeral and cost less.
- **Right-size the cluster.** Spark over-provisioning wastes money fast. Start small, measure, scale up only if you have a real performance need.
- **Photon for SQL-heavy silver builds.** Photon (Databricks' vectorized query engine) significantly outperforms standard Spark for SQL workloads. Worth the premium for steady-state pipelines.
- **DLT vs notebook jobs.** DLT has higher per-run overhead but handles a lot of operational concerns (data quality, lineage, retries) declaratively. For simple pipelines, plain notebook jobs are cheaper. For pipelines with complex DQ requirements and many tables, DLT pays for itself.

## Sources

- docs.databricks.com — Auto Loader, Delta Lake, Unity Catalog, Workflows.
- Architect's accumulated practice.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
