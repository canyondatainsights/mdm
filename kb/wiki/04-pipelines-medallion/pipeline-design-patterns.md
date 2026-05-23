# Pipeline Design Patterns

The patterns below recur across both Databricks and Snowflake implementations. They apply regardless of platform; the platform pages cover how to express them specifically.

## Idempotency

Every pipeline should be safely re-runnable without producing duplicate or inconsistent results. If you can't re-run a pipeline cleanly when something fails halfway through, you don't have a pipeline — you have a single-shot script.

Practical implementations:

- **MERGE/UPSERT, not INSERT.** Always. Use the source primary key as the merge key. A re-run of the same input produces the same target state.
- **Idempotent quarantine writes.** Quarantine table writes keyed on `(record_hash, run_id)` so the same failed record from the same run doesn't get logged twice.
- **Watermarks tracked in a control table.** Each pipeline run records its high-water mark (last processed timestamp or CDC sequence number). Restart picks up from there.
- **No side effects outside the table.** A pipeline that sends a Slack notification mid-run cannot be safely re-run. Notifications go at the end, gated on a successful transaction commit, or to an external service that handles dedup.

## CDC — change data capture

Incremental processing depends on knowing what changed. Three approaches:

**Source-system CDC.** The source database emits change events (Debezium, native CDC like SQL Server Change Tracking, Oracle GoldenGate). These land in bronze as change-event records and silver-layer processing applies them.

- Pro: low latency, low source-system impact.
- Con: requires source-system cooperation; not always available.

**Timestamp-based incremental extract.** A `last_modified_timestamp` column on the source. Extract `WHERE last_modified_timestamp > watermark`.

- Pro: simple, no special source-system tooling.
- Con: misses hard deletes. Misses records updated without timestamp bump. Clock-skew issues.

**Full snapshot with diff.** Daily full dump; compare to previous day's snapshot to find changes.

- Pro: catches deletes; doesn't depend on source-system metadata.
- Con: expensive for large tables; high latency.

For MDM purposes, CDC is preferred when available. Snapshot+diff is the fallback. Timestamp-based is the lowest-effort starting point but be aware of its limitations.

## Late-arriving data

Data arrives out of order. A transaction from yesterday shows up in tomorrow's batch because the source system was down. The pipeline must handle this without breaking historical aggregations.

Patterns:

- **Effective dating.** Records carry an *effective_from* timestamp distinct from *load_timestamp*. Queries filter on effective time. Late-arriving records simply fill in historical gaps.
- **Reprocessing windows.** Silver-layer materialization includes a re-process window — say, the last 7 days — every run. Late records within the window get picked up.
- **SCD Type 2 for slowly-changing dimensions.** Each version of a record has effective-from and effective-to. New version arriving with an older effective-from re-inserts in the correct historical position.

Databricks DLT supports late-arriving CDC natively via `AUTO CDC ... SEQUENCE BY`. Snowflake Streams require explicit handling but are no harder.

For MDM: late-arriving updates flow into landing tables and are processed by the normal stage/load cycle. The MDM history table captures the version sequence. No special handling needed in MDM itself; the upstream pipeline is where the lateness is reconciled.

## Quarantine, not drop

When a record fails validation, route it to a quarantine table. Do not drop silently. Do not fail the whole pipeline (unless the failure is so fundamental that processing more records would produce wrong results).

Quarantine table structure:

| Column | Purpose |
|---|---|
| `source_record` | The full original record (JSON or struct). |
| `failed_rule` | Which DQ rule failed. |
| `failure_reason` | Plain-text explanation. |
| `quarantine_timestamp` | When quarantined. |
| `pipeline_run_id` | Which pipeline run. |
| `status` | Open / Reviewed / Reprocessed / Rejected. |

Stewards work the quarantine table. Records can be edited and re-submitted, marked permanently rejected, or escalated. The quarantine should never be allowed to grow unbounded — depth is a key operational metric.

## Replayability

Pipelines should be able to reprocess history. Reasons it matters:

- A bug in a transformation is discovered. You want to re-run silver-from-bronze with the fix.
- A new column is added downstream and needs to be backfilled.
- A reference data correction changes the meaning of historical records.

Requirements for replayability:

- **Bronze is immutable.** You can always re-derive silver from bronze. If bronze is mutable, you can't.
- **Transformations are deterministic.** Same bronze input always produces the same silver output. No hidden state, no calls to changing external services without versioning. (AV calls are an exception — record AV version and treat as part of the input.)
- **Watermarks can be reset.** The control table allows resetting to a historical watermark for replay.
- **Time-travel on the platform.** Delta time-travel or Snowflake Time-Travel lets you query "what was the state of this table 30 days ago" and replay from that state.

## Schema evolution

Source systems add columns. Drop columns. Rename columns. Change types. Your pipeline must handle this without manual intervention for every change.

Strategies:

- **Schema-on-read in bronze.** Bronze stores raw structures (Parquet/JSON) without strict typing. Schema is applied at read time. New columns show up automatically.
- **Schema-on-write in silver.** Silver enforces a contract. New columns from bronze are either explicitly added to the contract (intentional) or ignored (default).
- **Auto Loader (Databricks)** supports automatic schema inference and evolution in bronze, with explicit control over what gets propagated.
- **Snowflake's INFER_SCHEMA** for COPY INTO supports similar patterns.
- **Versioned silver schemas.** Major contract changes get new schema versions; consumers migrate.

The architectural point: bronze tolerates source-system evolution; silver controls what propagates. Don't let silver be a passive mirror of bronze — silver is a contract, contracts are versioned.

## Lineage

You should always be able to answer "where did this value come from" for any value in any layer. Lineage is metadata.

- **Unity Catalog (Databricks)** captures lineage automatically across tables, columns, and notebooks/jobs.
- **Snowflake ACCESS_HISTORY** captures column-level lineage across queries.
- **Informatica MDM** captures lineage through XREF — but only within MDM. Upstream lineage from bronze through silver to landing needs external tooling (a metadata catalog or the platform's native capability).

When debugging "why is this BVT value wrong", lineage lets you trace: BVT cell → XREF contribution → MDM landing → silver row → bronze row → source-system file. Without lineage, debugging is archaeology.

## Cost discipline

Not architecture, but worth mentioning because it's where pipelines die: cost.

- **Bronze is cheap storage, expensive in volume.** Object storage is cheap; bronze can be retained long. But ingestion compute (Auto Loader, Snowpipe) accumulates.
- **Silver compute is the big one.** Cleansing, joining, validating — most of the compute work happens here. Optimize.
- **Gold compute amortizes across queries.** A gold table queried 1000 times a day is cheap per query, expensive to maintain. The math reverses for rarely-queried gold tables.

Common cost mistakes:

- **Re-running silver from full bronze every time.** Incremental.
- **Streaming where batch suffices.** Streaming compute runs 24/7.
- **Wide gold tables with mostly-unused columns.** Each column adds storage and compute.
- **Unbounded retention.** Bronze for 7 years is sometimes legitimate (regulatory), often just default. Set retention policies.

## Sources

- docs.databricks.com — DLT, Auto Loader, Delta Lake docs.
- docs.snowflake.com — Streams, Tasks, COPY INTO.
- Architect's accumulated practice.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
