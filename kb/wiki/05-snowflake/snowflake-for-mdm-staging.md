# Snowflake for MDM Staging

Snowflake is a strong fit for the medallion layers feeding an Informatica MDM hub. SQL-first development, separation of storage and compute, native time-travel, and Data Metric Functions for native quality monitoring make it operationally simpler than alternatives for many teams. This page covers the Snowflake-specific patterns relevant to standing up bronze/silver layers that feed MDM, and gold layers that consume from MDM.

## Architecture sketch

```
Source files / streams
   │
   ▼
[External Stage] or [Internal Stage]   ← S3/ADLS/GCS pointer, or Snowflake-managed
   │
   ▼
[Snowpipe] or [COPY INTO]              ← continuous or scheduled ingest
   │
   ▼
[bronze schema]                        ← raw, append-only
   │
   ▼  (Stream + Task, or dbt model)
[silver schema]                        ← cleansed, validated, conformed
   │
   ▼  (Snowflake → MDM via CDI or external table)
[Informatica MDM landing/staging/base-object]
   │
   ▼  (BVT materialization back to Snowflake)
[gold schema]                          ← consumer-shaped
   │
   ▼
[BI tools, downstream apps, reverse-ETL]
```

## Stages, Snowpipe, and COPY INTO

**External stages** point to cloud object storage (S3, ADLS, GCS) and let Snowflake read files in place without copying. Right for raw landing from sources that write to object storage.

**Internal stages** are Snowflake-managed object storage. Files uploaded via SnowSQL or driver `PUT`. Used when you don't have your own object storage to land files.

**Snowpipe** is continuous, event-driven ingestion. Files arriving in an external stage trigger Snowpipe to COPY INTO a bronze table within seconds. Right for streaming-like ingest from file drops.

**COPY INTO** is the bulk-loader, run by a task on a schedule or invoked from your orchestrator. Right for periodic batch loads.

Practical pattern for MDM source ingest:

- Source system writes daily extract files to S3.
- Snowpipe ingests to `bronze.<source>_<entity>_raw` as files arrive.
- A scheduled task processes new bronze rows into `silver.<entity>`.
- Snowflake-to-MDM connection (Informatica Cloud Data Integration) materializes silver into MDM landing on a schedule.

## Streams and Tasks for incremental processing

**Streams** are Snowflake's CDC primitive over a table. A stream tracks every insert/update/delete since the last time it was consumed. Querying the stream returns only the changes.

```sql
CREATE STREAM bronze.customer_changes ON TABLE bronze.customer_raw;

-- Then, in a task:
MERGE INTO silver.customer t
USING (SELECT * FROM bronze.customer_changes WHERE METADATA$ACTION != 'DELETE') s
ON t.source_id = s.source_id AND t.source_system = s.source_system
WHEN MATCHED THEN UPDATE SET ...
WHEN NOT MATCHED THEN INSERT ...;
```

**Tasks** are scheduled SQL. They can be cron-scheduled or chained (one task triggers when another completes). Combined with streams, they form the basis of an incremental pipeline:

```sql
CREATE TASK silver_customer_load
  WAREHOUSE = etl_wh
  SCHEDULE = 'USING CRON 0 * * * * UTC'    -- hourly
  WHEN SYSTEM$STREAM_HAS_DATA('bronze.customer_changes')
AS
  CALL silver.sp_load_customer();
```

The `WHEN SYSTEM$STREAM_HAS_DATA` clause is the cost-saver: the task only runs when there are actual changes. Suspended otherwise.

## Silver-layer cleansing in SQL

Snowflake's SQL handles most cleansing operations cleanly. Worth knowing:

- **String functions**: `TRIM`, `UPPER`, `LOWER`, `INITCAP`, `REPLACE`, `REGEXP_REPLACE`, `REGEXP_LIKE`.
- **`SOUNDEX(name)`** — built-in. Useful for blocking keys.
- **`EDITDISTANCE(s1, s2)`** — Levenshtein distance. Useful for fuzzy comparison in silver-layer dedup (within-source).
- **`JAROWINKLER_SIMILARITY(s1, s2)`** — built-in. Better than Levenshtein for name comparison.
- **`PARSE_JSON`**, **`OBJECT_KEYS`**, **VARIANT** type — for semi-structured ingest.
- **`TRY_CAST`** — type-safe casting that returns NULL instead of erroring. Pair with quarantine for records where the cast fails.

For more advanced cleansing — name parsing, address verification — you'll typically call out to external services (UDF backed by Snowpark Python, external function calling an API). Pure SQL handles 80% of silver-layer needs.

## Snowpark for non-SQL logic

When cleansing logic exceeds SQL's comfortable expressiveness, Snowpark Python is the next step. Snowpark runs Python inside Snowflake's compute, with access to the data as DataFrames (PySpark-like API).

Use Snowpark for:

- Complex name/address parsing that needs Python libraries (e.g., `nameparser`, `usaddress`).
- ML-based scoring (fuzzy match scoring, anomaly detection) inside silver.
- Calling out to external services (with the appropriate external access integration).

Don't use Snowpark when SQL would do. Snowpark adds complexity and compute overhead.

## Materializing to MDM landing

Three connection patterns:

**Informatica Cloud Data Integration** (CDI) maps Snowflake silver tables to MDM landing tables. Scheduled or event-triggered. The most common pattern for MDM SaaS.

**External tables** — MDM points at a Snowflake table via an external table or federated query. Reads happen on demand. Lower latency, more direct, less explicit handoff.

**File drop** — Snowflake unloads silver to a staging area (S3); MDM ingests from there. Adds latency but works in environments where direct Snowflake-MDM connection isn't allowed.

For new programs, CDI is the recommended default — it's the supported path and gives operational visibility.

## Receiving the BVT back

After MDM resolves the golden record, you want it back in Snowflake to serve gold-layer consumers.

```
MDM BVT view → CDI extract → Snowflake gold.customer_master
```

The gold table should not be a verbatim copy of the BVT — it should be reshaped for consumers. Drop MDM internal IDs. Flatten relationships. Pre-join with reference data. Add business-friendly column names.

Pattern: a gold `customer_master` table with consolidated customer attributes, plus a `customer_xref` table that maps each source-system customer ID to the MDM-resolved customer ID. Consumers join `customer_xref` from their source's view to get the resolved master.

## Time travel and audit

Snowflake's Time Travel feature lets you query historical state up to 90 days (Enterprise edition). This is invaluable for:

- **Reprocessing.** Replay silver-from-bronze with a fix without needing a separate snapshot.
- **Audit.** "What did the customer table look like the day this report was generated?" — query at the historical timestamp.
- **Recovery.** Accidental DELETE or DROP can be undone within retention.

Configure retention based on your audit and recovery needs. The default is 1 day; 7-30 days is typical for important tables; longer for regulated data.

## Cost notes

- **Warehouses auto-suspend.** Set short auto-suspend (60s) for ETL warehouses; they run when needed and stop. The cost model is pay-per-second of compute, not pay-per-query.
- **Separate warehouses for ETL, BI, ML.** Different sizing, different scheduling, different priorities. Mixing them means one workload starves another.
- **Snowpipe is per-file.** Many small files are more expensive than fewer larger files. Batch source-system writes when you can.
- **Storage is cheap, compute is dear.** Don't optimize for storage savings if it means more compute (e.g., excessive normalization that forces joins on every gold query).

## Sources

- docs.snowflake.com — Stages, Snowpipe, Streams, Tasks, Snowpark.
- snowflakemasters.in: *Learn Snowflake Data Pipeline Best Practices in 2026*.
- Architect's accumulated practice.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
