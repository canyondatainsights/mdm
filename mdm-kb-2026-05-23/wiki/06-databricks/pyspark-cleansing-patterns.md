# PySpark Cleansing Patterns

The Databricks counterpart to [`05-snowflake/sql-cleansing-patterns.md`](../05-snowflake/sql-cleansing-patterns.md). Practical PySpark patterns for silver-layer cleansing before MDM. SQL works equivalently in most cases; PySpark earns its place when the logic exceeds SQL's comfortable expressiveness or when reusable Python libraries (phone parsing, address libraries, ML scoring) come into play.

## Basic cleansing

```python
from pyspark.sql.functions import col, trim, lower, upper, regexp_replace, when, initcap

cleaned = (df
  .withColumn("first_name", initcap(trim(regexp_replace(col("first_name"), r"\s+", " "))))
  .withColumn("last_name", initcap(trim(regexp_replace(col("last_name"), r"\s+", " "))))
  .withColumn("email", lower(trim(col("email"))))
)

# Replace placeholder values with null
cleaned = cleaned.withColumn(
  "first_name",
  when(upper(trim(col("first_name"))).isin("TEST", "UNKNOWN", "N/A", "NA", "NULL", ""), None)
    .otherwise(col("first_name"))
)
```

## Email validation

```python
from pyspark.sql.functions import expr

email_pattern = r"^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$"

with_status = df.withColumn(
  "email_status",
  when(col("email").isNull() | (trim(col("email")) == ""), "NULL_OR_BLANK")
    .when(~col("email").rlike(email_pattern), "INVALID_FORMAT")
    .when(col("email").rlike(r"@(example|test|invalid)\."), "PLACEHOLDER_DOMAIN")
    .otherwise("VALID")
)
```

## Phone normalization with the `phonenumbers` library

This is where PySpark pulls ahead of pure SQL. Multi-country phone parsing is hard; libraries exist; use them.

```python
from pyspark.sql.functions import udf
from pyspark.sql.types import StringType
import phonenumbers

def normalize_phone(phone_raw, country_code):
    if phone_raw is None:
        return None
    try:
        parsed = phonenumbers.parse(phone_raw, country_code or "US")
        if phonenumbers.is_valid_number(parsed):
            return phonenumbers.format_number(parsed, phonenumbers.PhoneNumberFormat.E164)
    except phonenumbers.NumberParseException:
        pass
    return None

normalize_phone_udf = udf(normalize_phone, StringType())

cleaned = df.withColumn("phone_e164", normalize_phone_udf(col("phone"), col("country")))
```

UDFs have a performance cost. For high-volume tables, consider:

- A pandas UDF (vectorized, much faster).
- Reimplementing in Scala if Python overhead is the bottleneck.
- Native Spark functions where possible (regexp_extract for simple cases).

## Deduplication within a source

```python
from pyspark.sql.window import Window
from pyspark.sql.functions import row_number, desc

w = Window.partitionBy("source_system", "source_id").orderBy(desc("extract_ts"))

deduped = (df
  .withColumn("rn", row_number().over(w))
  .filter(col("rn") == 1)
  .drop("rn")
)
```

For deduplication by a derived natural key:

```python
w = Window.partitionBy(lower(col("email"))).orderBy(desc("extract_ts"))

deduped = (df
  .filter(col("email").isNotNull())
  .withColumn("rn", row_number().over(w))
  .filter(col("rn") == 1)
  .drop("rn")
)
```

## Blocking key generation

```python
from pyspark.sql.functions import soundex, substring

with_blocks = (df
  .withColumn("block_lastname_soundex", soundex(col("last_name")))
  .withColumn(
    "block_name",
    expr("""
      concat(
        substring(regexp_replace(upper(last_name), '[^A-Z]', ''), 1, 5),
        coalesce(substring(regexp_replace(upper(first_name), '[^A-Z]', ''), 1, 1), '_')
      )
    """)
  )
  .withColumn(
    "block_postal",
    expr("coalesce(substring(regexp_replace(postal_code, '[^0-9A-Z]', ''), 1, 3), '___')")
  )
)
```

Spark's `soundex()` is built-in. For more sophisticated phonetic encoding (metaphone, NYSIIS) you need a UDF or external library — `fuzzy` and `jellyfish` are common Python choices.

## Fuzzy matching within a source

When the source has duplicates that don't share an exact key but should be collapsed before MDM:

```python
from pyspark.sql.functions import levenshtein

# Self-join on blocking key
candidates = (df.alias("a")
  .join(df.alias("b"), [
    col("a.block_lastname_soundex") == col("b.block_lastname_soundex"),
    col("a.source_id") < col("b.source_id")  # avoid self and dup pairs
  ])
  .select(
    col("a.source_id").alias("id_a"),
    col("b.source_id").alias("id_b"),
    col("a.first_name").alias("fn_a"),
    col("b.first_name").alias("fn_b"),
    col("a.last_name").alias("ln_a"),
    col("b.last_name").alias("ln_b")
  )
)

scored = candidates.withColumn(
  "lastname_distance",
  levenshtein(col("ln_a"), col("ln_b"))
)

matches = scored.filter(col("lastname_distance") <= 2)
```

For better fuzzy scoring, use a UDF backed by `rapidfuzz`:

```python
from rapidfuzz import fuzz
from pyspark.sql.types import IntegerType

@udf(returnType=IntegerType())
def name_similarity(name1, name2):
    if name1 is None or name2 is None:
        return 0
    return fuzz.ratio(name1.lower(), name2.lower())

scored = candidates.withColumn(
  "lastname_sim",
  name_similarity(col("ln_a"), col("ln_b"))
).filter(col("lastname_sim") >= 85)
```

Again — this is for in-silver dedup. Cross-source matching is MDM's job.

## Name parsing

`nameparser` library handles Western name parsing competently:

```python
from nameparser import HumanName
from pyspark.sql.types import StructType, StructField, StringType

name_schema = StructType([
  StructField("title", StringType(), True),
  StructField("first", StringType(), True),
  StructField("middle", StringType(), True),
  StructField("last", StringType(), True),
  StructField("suffix", StringType(), True),
])

@udf(returnType=name_schema)
def parse_name(full_name):
    if not full_name:
        return None
    n = HumanName(full_name)
    return (n.title, n.first, n.middle, n.last, n.suffix)

parsed = df.withColumn("name_parts", parse_name(col("full_name")))
parsed = parsed.select(
  "*",
  col("name_parts.first").alias("first_name"),
  col("name_parts.last").alias("last_name"),
  col("name_parts.middle").alias("middle_name"),
  col("name_parts.suffix").alias("suffix"),
  col("name_parts.title").alias("title")
).drop("name_parts")
```

Caveat: `nameparser` is biased toward Western name structures. For genuinely global name parsing, consider Informatica's name parsing libraries (callable from a service endpoint) or a commercial tool.

## Quarantine routing

```python
from pyspark.sql.functions import current_timestamp, lit

email_pattern = r"^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$"

valid = df.filter(col("email").rlike(email_pattern))
invalid = (df
  .filter(~col("email").rlike(email_pattern))
  .withColumn("quarantined_at", current_timestamp())
  .withColumn("failure_reason", lit("EMAIL_INVALID_FORMAT"))
  .withColumn("status", lit("OPEN"))
)

valid.write.mode("append").saveAsTable("silver.customer")
invalid.write.mode("append").saveAsTable("silver_quarantine.customer_failed")
```

## Putting it together — a silver-layer customer build (DLT)

```python
import dlt
from pyspark.sql.functions import col, trim, lower, upper, regexp_replace, initcap, when, row_number, soundex, current_timestamp
from pyspark.sql.window import Window

@dlt.table(
  name="silver_customer",
  comment="Cleansed customer records ready for MDM landing"
)
@dlt.expect_or_drop("has_keys", "source_system IS NOT NULL AND source_id IS NOT NULL")
@dlt.expect("has_contact", "email IS NOT NULL OR phone IS NOT NULL")
@dlt.expect("valid_email", "email IS NULL OR email RLIKE '^[A-Za-z0-9._%+\\-]+@[A-Za-z0-9.\\-]+\\.[A-Za-z]{2,}$'")
def silver_customer():
    bronze = dlt.read_stream("bronze.customer_raw")

    w = Window.partitionBy("source_system", "source_id").orderBy(col("extract_ts").desc())

    return (bronze
      # cleansing
      .withColumn("first_name", initcap(trim(regexp_replace(col("first_name"), r"\s+", " "))))
      .withColumn("last_name", initcap(trim(regexp_replace(col("last_name"), r"\s+", " "))))
      .withColumn("email", lower(trim(col("email"))))
      .withColumn("phone", regexp_replace(col("phone"), r"[^0-9+]", ""))
      .withColumn("postal_code", upper(regexp_replace(col("postal_code"), r"\s", "")))
      # placeholder cleanup
      .withColumn(
        "first_name",
        when(upper(col("first_name")).isin("TEST","UNKNOWN","N/A","NA","NULL",""), None)
          .otherwise(col("first_name"))
      )
      # dedup
      .withColumn("rn", row_number().over(w))
      .filter(col("rn") == 1)
      .drop("rn")
      # blocking key for downstream MDM
      .withColumn("block_lastname_soundex", soundex(col("last_name")))
      .withColumn("silver_load_ts", current_timestamp())
    )
```

This is a starting template. Production versions add reference-data joins, address verification calls, and orchestration into MDM landing.

## Sources

- docs.databricks.com — PySpark API, DLT.
- Architect's accumulated practice.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
