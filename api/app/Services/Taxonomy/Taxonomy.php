<?php

namespace App\Services\Taxonomy;

use App\Models\TaxonomyTerm;
use Illuminate\Support\Facades\Schema;

/**
 * Reads the lockable taxonomy from the DB reference table (taxonomy_terms), so subjects,
 * products and vendors can be expanded at runtime from the admin without a code change.
 *
 * Falls back to config('mdm.dimensions' / 'products') when the table is absent (pre-migration)
 * or empty (un-seeded) — so fresh installs and tests work unchanged. Memoized per process;
 * call flush() after writing terms within the same request.
 */
class Taxonomy
{
    /** @var array{dimensions:array<string,array<int,string>>,products:array<string,array<int,string>>}|null */
    private static ?array $memo = null;

    private static function load(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $fromConfig = [
            'dimensions' => (array) config('mdm.dimensions', []),
            'products' => (array) config('mdm.products', []),
        ];

        try {
            if (! Schema::hasTable('taxonomy_terms')) {
                return self::$memo = $fromConfig;
            }
            $rows = TaxonomyTerm::query()->where('active', true)
                ->orderBy('sort_order')->orderBy('value')->get();
        } catch (\Throwable) {
            return self::$memo = $fromConfig;
        }

        if ($rows->isEmpty()) {
            return self::$memo = $fromConfig;
        }

        $dims = ['mdm_vendor' => [], 'data_platform' => [], 'financial_model' => [], 'domain' => []];
        $products = [];
        foreach ($rows as $r) {
            if ($r->type === 'product') {
                $products[$r->vendor ?: '_'][] = $r->value;
            } elseif (array_key_exists($r->type, $dims)) {
                $dims[$r->type][] = $r->value;
            }
        }

        return self::$memo = ['dimensions' => $dims, 'products' => $products];
    }

    /** Drop the per-process cache (after editing terms). */
    public static function flush(): void
    {
        self::$memo = null;
    }

    /** @return array<string,array<int,string>> e.g. ['mdm_vendor'=>[...], 'data_platform'=>[...], ...] */
    public static function dimensions(): array
    {
        return self::load()['dimensions'];
    }

    /** @return array<string,array<int,string>> keyed by vendor slug, e.g. ['informatica'=>[...]] */
    public static function products(): array
    {
        return self::load()['products'];
    }

    /** Values for a single dimension type. @return array<int,string> */
    public static function values(string $type): array
    {
        return self::load()['dimensions'][$type] ?? [];
    }

    /** Product names for one vendor. @return array<int,string> */
    public static function productsFor(?string $vendor): array
    {
        return self::load()['products'][$vendor] ?? [];
    }

    /** Flattened, de-duplicated list of every product across vendors. @return array<int,string> */
    public static function allProducts(): array
    {
        $all = self::load()['products'];

        return $all ? array_values(array_unique(array_merge([], ...array_values($all)))) : [];
    }

    /** Select-options shape ([value => label]) for a dimension type. @return array<string,string> */
    public static function options(string $type): array
    {
        $out = [];
        foreach (self::values($type) as $v) {
            $out[$v] = $v;
        }

        return $out;
    }
}
