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
     * @return array{mdm_vendor:?string,data_platform:?string,financial_model:?string,domain:string,scope:string,page_updated_at:?string,title:?string}
     */
    public static function resolve(string $relPath, array $frontMatter, ?string $body = null): array
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
            'title' => $fm(['title'], null),
            'page_updated_at' => $fm(['updated', 'page_updated_at'], $body ? self::lastRevisionDate($body) : null),
        ];

        // Normalize empty strings / "null" to null.
        foreach (['mdm_vendor', 'data_platform', 'financial_model'] as $k) {
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
        foreach (self::DOMAIN_KEYWORDS as $kw => $domain) {
            if (str_contains($name, $kw)) {
                return $domain;
            }
        }

        return 'general';
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
