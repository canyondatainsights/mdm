# Snowflake SQL Cleansing Patterns

A reference of practical SQL patterns for the cleansing and standardization work that happens in a Snowflake silver layer feeding MDM. All examples are runnable against a Snowflake account with the appropriate schemas.

## Basic cleansing

**Trim, case, collapse whitespace.**

```sql
SELECT
  TRIM(REGEXP_REPLACE(first_name, '\\s+', ' ')) AS first_name_clean,
  TRIM(REGEXP_REPLACE(last_name, '\\s+', ' ')) AS last_name_clean,
  LOWER(TRIM(email)) AS email_clean
FROM bronze.customer_raw;
```

**Replace placeholder values with NULL.**

```sql
SELECT
  IFF(
    UPPER(TRIM(first_name)) IN ('TEST', 'UNKNOWN', 'N/A', 'NA', 'NULL', 'XXX', ''),
    NULL,
    INITCAP(TRIM(first_name))
  ) AS first_name_clean
FROM bronze.customer_raw;
```

**Strip non-printable characters.**

```sql
SELECT
  REGEXP_REPLACE(description, '[\\x00-\\x1F\\x7F]', '') AS description_clean
FROM bronze.product_raw;
```

## Phone normalization

```sql
SELECT
  phone_raw,
  -- Strip everything except digits and leading +
  REGEXP_REPLACE(phone_raw, '[^0-9+]', '') AS phone_digits,
  -- Add country code if missing (US assumption — verify with country column!)
  CASE
    WHEN REGEXP_REPLACE(phone_raw, '[^0-9+]', '') RLIKE '^\\+'
      THEN REGEXP_REPLACE(phone_raw, '[^0-9+]', '')
    WHEN LENGTH(REGEXP_REPLACE(phone_raw, '[^0-9]', '')) = 10
      THEN '+1' || REGEXP_REPLACE(phone_raw, '[^0-9]', '')
    WHEN LENGTH(REGEXP_REPLACE(phone_raw, '[^0-9]', '')) = 11
         AND REGEXP_REPLACE(phone_raw, '[^0-9]', '') LIKE '1%'
      THEN '+' || REGEXP_REPLACE(phone_raw, '[^0-9]', '')
    ELSE NULL  -- unparseable; quarantine
  END AS phone_e164
FROM bronze.customer_raw;
```

For real multi-country phone parsing, use Snowpark Python with the `phonenumbers` library. SQL alone is fine when you can constrain to known formats.

## Email validation

```sql
SELECT
  email,
  CASE
    WHEN email IS NULL OR TRIM(email) = '' THEN 'NULL_OR_BLANK'
    WHEN NOT REGEXP_LIKE(email, '^[A-Za-z0-9._%+\\-]+@[A-Za-z0-9.\\-]+\\.[A-Za-z]{2,}$')
      THEN 'INVALID_FORMAT'
    WHEN email LIKE '%@example.com' OR email LIKE '%@test.com'
      THEN 'PLACEHOLDER_DOMAIN'
    ELSE 'VALID'
  END AS email_status,
  LOWER(TRIM(email)) AS email_clean
FROM bronze.customer_raw;
```

## Deduplication within a source

The pattern: keep the most recent record per source primary key.

```sql
SELECT *
FROM (
  SELECT
    *,
    ROW_NUMBER() OVER (
      PARTITION BY source_system, source_id
      ORDER BY extract_timestamp DESC
    ) AS rn
  FROM bronze.customer_raw
) WHERE rn = 1;
```

For deduplication where the primary key isn't reliable but a natural key emerges from cleaning:

```sql
WITH cleaned AS (
  SELECT
    customer_id,
    LOWER(TRIM(email)) AS email_norm,
    UPPER(TRIM(last_name)) AS last_name_norm,
    extract_timestamp,
    *
  FROM bronze.customer_raw
  WHERE email IS NOT NULL
),
ranked AS (
  SELECT
    *,
    ROW_NUMBER() OVER (
      PARTITION BY email_norm
      ORDER BY extract_timestamp DESC
    ) AS rn
  FROM cleaned
)
SELECT * EXCLUDE (rn) FROM ranked WHERE rn = 1;
```

## Blocking key generation

For downstream fuzzy matching in MDM (or for within-Snowflake fuzzy dedup), generate blocking keys in silver:

```sql
SELECT
  customer_id,
  first_name,
  last_name,
  -- Soundex of last name — phonetic blocking
  SOUNDEX(last_name) AS block_lastname_soundex,
  -- First 5 chars of normalized last name + first initial
  SUBSTR(REGEXP_REPLACE(UPPER(last_name), '[^A-Z]', ''), 1, 5)
    || COALESCE(SUBSTR(REGEXP_REPLACE(UPPER(first_name), '[^A-Z]', ''), 1, 1), '_')
    AS block_name,
  -- Postal code prefix (US ZIP first 3, or country-specific)
  COALESCE(SUBSTR(REGEXP_REPLACE(postal_code, '[^0-9A-Z]', ''), 1, 3), '___')
    AS block_postal
FROM silver.customer;
```

These columns flow into MDM staging where they're used by the match engine's blocking.

## Fuzzy comparison within Snowflake

When you want to dedupe more aggressively within a source than blocking + exact match allows:

```sql
WITH candidates AS (
  -- All pairs sharing a Soundex
  SELECT
    a.customer_id AS id_a,
    b.customer_id AS id_b,
    a.first_name AS fn_a, b.first_name AS fn_b,
    a.last_name AS ln_a, b.last_name AS ln_b
  FROM silver.customer a
  JOIN silver.customer b
    ON SOUNDEX(a.last_name) = SOUNDEX(b.last_name)
   AND a.customer_id < b.customer_id   -- avoid self and dup pairs
)
SELECT
  *,
  JAROWINKLER_SIMILARITY(fn_a, fn_b) AS firstname_sim,
  JAROWINKLER_SIMILARITY(ln_a, ln_b) AS lastname_sim,
  (JAROWINKLER_SIMILARITY(fn_a, fn_b) + JAROWINKLER_SIMILARITY(ln_a, ln_b)) / 2 AS combined_sim
FROM candidates
WHERE JAROWINKLER_SIMILARITY(fn_a, fn_b) > 70
  AND JAROWINKLER_SIMILARITY(ln_a, ln_b) > 80;
```

Note: this is for within-Snowflake dedup before MDM. **Don't use this as a substitute for MDM's match engine across sources.** MDM's SSAName3 is more sophisticated than Jaro-Winkler for cross-source name matching, especially across languages and with title/suffix handling.

## Address normalization (light)

Heavy address verification belongs in an AV engine. For light pre-cleaning before AV:

```sql
SELECT
  -- Uppercase and trim
  UPPER(TRIM(REGEXP_REPLACE(address_line_1, '\\s+', ' '))) AS address_1_norm,
  -- Common abbreviation standardization
  CASE
    WHEN UPPER(address_line_1) RLIKE '\\bSTREET\\b' THEN REGEXP_REPLACE(UPPER(address_line_1), '\\bSTREET\\b', 'ST')
    WHEN UPPER(address_line_1) RLIKE '\\bAVENUE\\b' THEN REGEXP_REPLACE(UPPER(address_line_1), '\\bAVENUE\\b', 'AVE')
    -- ... etc
    ELSE UPPER(address_line_1)
  END AS address_1_std,
  -- Postal code normalization
  REGEXP_REPLACE(UPPER(postal_code), '\\s', '') AS postal_code_norm
FROM bronze.customer_raw;
```

Then pass to AV for the real work.

## Date handling

Don't trust source-provided date strings. Parse explicitly:

```sql
SELECT
  date_raw,
  TRY_TO_DATE(date_raw, 'YYYY-MM-DD') AS date_iso,
  TRY_TO_DATE(date_raw, 'MM/DD/YYYY') AS date_us,
  TRY_TO_DATE(date_raw, 'DD/MM/YYYY') AS date_eu,
  -- COALESCE in expected order of likelihood, given the source's locale
  COALESCE(
    TRY_TO_DATE(date_raw, 'YYYY-MM-DD'),
    TRY_TO_DATE(date_raw, 'MM/DD/YYYY')
  ) AS date_parsed
FROM bronze.customer_raw;
```

Records where `date_parsed` is NULL go to quarantine.

## Reference data joins

Standardization against a reference table:

```sql
WITH country_xwalk AS (
  SELECT
    source_code,
    target_code AS canonical_country
  FROM reference.country_crosswalk
  WHERE source_system = 'CRM'
    AND CURRENT_DATE BETWEEN effective_from AND COALESCE(effective_to, '9999-12-31')
)
SELECT
  c.*,
  COALESCE(x.canonical_country, c.country) AS country_std
FROM bronze.customer_raw c
LEFT JOIN country_xwalk x
  ON c.country = x.source_code;
```

The effective-date join guarantees you get the right crosswalk for the data's effective time. If reference data changes are time-bounded (a country code was retired), historical records still resolve correctly.

## Quarantine pattern

When silver-layer validation fails, write to quarantine instead of dropping:

```sql
INSERT INTO silver_quarantine.customer_failed
SELECT
  bronze.*,
  CURRENT_TIMESTAMP() AS quarantined_at,
  'EMAIL_INVALID_FORMAT' AS failure_reason,
  'OPEN' AS status
FROM bronze.customer_raw bronze
WHERE NOT REGEXP_LIKE(email, '^[A-Za-z0-9._%+\\-]+@[A-Za-z0-9.\\-]+\\.[A-Za-z]{2,}$')
  AND email IS NOT NULL;
```

The `OPEN` status drops to `REPROCESSED` or `REJECTED` after a steward acts.

## Putting it together — a silver-layer customer load

```sql
INSERT INTO silver.customer
WITH cleaned AS (
  SELECT
    source_system,
    source_id,
    INITCAP(TRIM(first_name)) AS first_name,
    INITCAP(TRIM(last_name)) AS last_name,
    LOWER(TRIM(email)) AS email,
    REGEXP_REPLACE(phone, '[^0-9+]', '') AS phone,
    address_line_1,
    UPPER(TRIM(city)) AS city,
    UPPER(TRIM(state)) AS state,
    REGEXP_REPLACE(UPPER(postal_code), '\\s', '') AS postal_code,
    extract_timestamp
  FROM bronze.customer_raw
  WHERE extract_timestamp > (SELECT COALESCE(MAX(extract_timestamp), '1900-01-01') FROM silver.customer)
),
deduped AS (
  SELECT *
  FROM (
    SELECT
      *,
      ROW_NUMBER() OVER (
        PARTITION BY source_system, source_id
        ORDER BY extract_timestamp DESC
      ) AS rn
    FROM cleaned
  ) WHERE rn = 1
),
validated AS (
  SELECT
    *,
    REGEXP_LIKE(email, '^[A-Za-z0-9._%+\\-]+@[A-Za-z0-9.\\-]+\\.[A-Za-z]{2,}$') AS email_valid,
    LENGTH(phone) BETWEEN 10 AND 15 AS phone_valid
  FROM deduped
)
SELECT
  source_system,
  source_id,
  first_name,
  last_name,
  IFF(email_valid, email, NULL) AS email,
  IFF(phone_valid, phone, NULL) AS phone,
  address_line_1,
  city,
  state,
  postal_code,
  extract_timestamp,
  CURRENT_TIMESTAMP() AS silver_load_timestamp,
  SOUNDEX(last_name) AS block_lastname_soundex
FROM validated;
```

This is a starting point. Production versions add quarantine routing, MERGE for upserts, schema evolution handling, and observability hooks.

## Sources

- docs.snowflake.com — String functions, regular expressions, fuzzy match functions.
- Architect's accumulated practice.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
