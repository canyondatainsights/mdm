<?php

namespace App\Services\Kb;

/**
 * Resolves vendor-isolation metadata for a KB file.
 *
 * Precedence: explicit YAML front-matter wins; otherwise we derive sensible defaults
 * from the wiki section (path) and filename. This is what makes a chunk filterable so
 * that, e.g., a Snowflake page never surfaces in a Databricks-locked conversation.
 */
class Metadata
{
    /** section prefix => [mdm_vendor, data_platform, financial_model, scope] */
    private const SECTION_MAP = [
        '01-foundations'        => [null, null, null, 'neutral'],
        '02-informatica-mdm'    => ['informatica', null, null, 'vendor-specific'],
        '03-data-quality'       => ['informatica', null, null, 'vendor-specific'],
        '04-pipelines-medallion'=> [null, null, null, 'neutral'],
        '05-snowflake'          => [null, 'snowflake', null, 'vendor-specific'],
        '06-databricks'         => [null, 'databricks', null, 'vendor-specific'],
        '07-governance-consent' => [null, null, null, 'neutral'],
        '08-patterns-playbooks' => ['informatica', null, null, 'vendor-specific'],
        '09-decisions-adrs'     => [null, null, null, 'neutral'],
        '10-financial-data-models' => [null, null, null, 'neutral'],
        // vendor scaffolds
        'sap'      => ['sap', null, null, 'vendor-specific'],
        'profisee' => ['profisee', null, null, 'vendor-specific'],
        'reltio'   => ['reltio', null, null, 'vendor-specific'],
        'ataccama' => ['ataccama', null, null, 'vendor-specific'],
    ];

    /** keyword in filename => domain */
    private const DOMAIN_KEYWORDS = [
        'customer' => 'customer',
        'supplier' => 'supplier',
        'vendor' => 'vendor',
        'product' => 'product',
        'finance' => 'finance', 'financial' => 'finance', 'fpml' => 'finance', 'cdm' => 'finance',
        'health' => 'healthcare', 'hipaa' => 'healthcare',
    ];

    /**
     * @param  array<string,mixed>  $frontMatter
     * @param  array<string,mixed>  $overrides  Explicit values (e.g. from the upload form) that win over derivation.
     * @return array{mdm_vendor:?string,data_platform:?string,financial_model:?string,domain:string,scope:string,product:?string,product_version:?string,page_updated_at:?string,title:?string}
     */
    public static function resolve(string $relPath, array $frontMatter, ?string $body = null, array $overrides = []): array
    {
        [$vendor, $platform, $financial, $scope] = self::sectionDefaults($relPath);
        $domain = self::domainFromPath($relPath);

        // Front-matter overrides (accept a few aliases).
        $fm = fn (array $keys, $default) => self::first($frontMatter, $keys) ?? $default;

        $meta = [
            'mdm_vendor' => $fm(['vendor', 'mdm_vendor'], $vendor),
            'data_platform' => $fm(['platform', 'data_platform'], $platform),
            'financial_model' => $fm(['financial_model', 'finmodel'], $financial),
            'domain' => $fm(['domain'], $domain),
            'scope' => $fm(['scope'], $scope),
            'product' => $fm(['product'], null),
            'product_version' => $fm(['version', 'product_version'], null),
            'extension' => $fm(['extension'], null),
            'title' => $fm(['title'], null),
            'page_updated_at' => $fm(['updated', 'page_updated_at'], $body ? self::lastRevisionDate($body) : null),
        ];

        // Explicit overrides (upload form) take precedence over anything derived.
        foreach (['mdm_vendor', 'data_platform', 'financial_model', 'domain', 'scope', 'product', 'product_version', 'extension', 'title'] as $k) {
            if (array_key_exists($k, $overrides) && ! in_array($overrides[$k], ['', null], true)) {
                $meta[$k] = is_string($overrides[$k]) ? trim($overrides[$k]) : $overrides[$k];
            }
        }

        // Auto-parse CONSERVATIVELY — from the filename + title region only, never deep body
        // content. A doc that merely *mentions* Databricks/Snowflake or "customer" is not a
        // Databricks/customer doc; guessing from incidental mentions mis-tags and then the
        // isolation filters hide the doc. When unsure, leave null / 'general' (the filename
        // already set the domain) and let an explicit upload tag or Phase-B classification decide.
        $titleish = strtolower(basename($relPath).' '.substr((string) $body, 0, 400));
        if (empty($meta['mdm_vendor'])) {
            $meta['mdm_vendor'] = self::detectFromList($titleish, \App\Services\Taxonomy\Taxonomy::values('mdm_vendor'));
        }
        // data_platform is NOT inferred from content — only an explicit tag/front-matter/section.
        if (empty($meta['product'])) {
            $candidates = \App\Services\Taxonomy\Taxonomy::productsFor($meta['mdm_vendor'] ?? '_')
                ?: \App\Services\Taxonomy\Taxonomy::allProducts();
            $meta['product'] = self::detectProduct($titleish, $candidates);
        }
        if (empty($meta['product_version'])) {
            $meta['product_version'] = self::detectVersion(strtolower(basename($relPath)));
        }

        // Normalize empty strings / "null" to null.
        foreach (['mdm_vendor', 'data_platform', 'financial_model', 'product', 'product_version', 'extension'] as $k) {
            if (in_array($meta[$k], ['', 'null', 'none', null], true)) {
                $meta[$k] = null;
            }
        }

        return $meta;
    }

    private static function sectionDefaults(string $relPath): array
    {
        foreach (self::SECTION_MAP as $needle => $vals) {
            if (str_contains($relPath, '/'.$needle.'/') || str_contains($relPath, $needle.'/')) {
                return $vals;
            }
        }

        return [null, null, null, 'neutral'];
    }

    private static function domainFromPath(string $relPath): string
    {
        $name = strtolower(basename($relPath));
        // Vertical/extension docs (e.g. "customer360forinsurance", "supplier360extensionforsap",
        // "mdmextensionformicrosoftfabric") are not a plain data-domain — don't infer one from a
        // filename keyword; leave 'general' and let classification assign domain + extension.
        if (preg_match('/(extensionfor|360for[a-z]|for(insurance|retail|healthcare|banking|lifesciences|esg|sap)\b)/', $name)) {
            return 'general';
        }
        foreach (self::DOMAIN_KEYWORDS as $kw => $domain) {
            if (str_contains($name, $kw)) {
                return $domain;
            }
        }

        return 'general';
    }

    /** First value from $list (lowercase tokens, e.g. vendors) that appears in the haystack. */
    private static function detectFromList(string $hay, array $list): ?string
    {
        foreach ($list as $v) {
            if ($v !== '' && str_contains($hay, strtolower($v))) {
                return $v;
            }
        }

        return null;
    }

    /** Longest configured product name that appears in the haystack. */
    private static function detectProduct(string $hay, array $products): ?string
    {
        usort($products, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));
        foreach ($products as $p) {
            if ($p !== '' && str_contains($hay, strtolower($p))) {
                return $p;
            }
        }

        return null;
    }

    /** Detect a version: explicit "version/release/v X.Y" first, else an x.y token in the filename. */
    private static function detectVersion(string $s): ?string
    {
        if (preg_match('/\b(?:version|release|v|r)\s*[:\-]?\s*(\d+\.\d+(?:\.\d+)?)/i', $s, $m)) {
            return $m[1];
        }
        if (preg_match('/\b(\d{1,2}\.\d{1,2}(?:\.\d+)?)\b/', $s, $m)) {
            return $m[1];
        }

        return null;
    }

    private static function first(array $arr, array $keys)
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $arr) && $arr[$k] !== null) {
                return is_string($arr[$k]) ? trim($arr[$k]) : $arr[$k];
            }
        }

        return null;
    }

    /** Pull the most recent YYYY-MM-DD from a page's Revision log table. */
    private static function lastRevisionDate(string $body): ?string
    {
        if (preg_match_all('/\b(20\d{2}-\d{2}-\d{2})\b/', $body, $m)) {
            $dates = $m[1];
            rsort($dates);

            return $dates[0];
        }

        return null;
    }
}
