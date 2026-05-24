<?php

namespace App\Services\Kb;

use App\Models\TaxonomyTerm;
use App\Services\Taxonomy\Taxonomy;
use Illuminate\Support\Str;

/**
 * Shared upload-tagging logic used by both the API (UploadController) and the Filament admin
 * upload: builds whitelisted ingestion overrides from a tag set, computes the destination folder,
 * and persists approved new subjects to the taxonomy. Keeps the two upload paths identical.
 */
class UploadTagger
{
    /** Build ingestion overrides from a tag set, whitelisting dimension values against the taxonomy. */
    public function buildOverrides(array $tags, ?array $dims = null): array
    {
        $dims ??= Taxonomy::dimensions();

        $vendor = $this->inList($tags['mdm_vendor'] ?? null, $dims['mdm_vendor']);
        $platform = $this->inList($tags['data_platform'] ?? null, $dims['data_platform']);
        $domain = $this->inList($tags['domain'] ?? null, $dims['domain']);
        $product = isset($tags['product']) ? Str::limit(trim((string) $tags['product']), 128, '') : null;
        $version = isset($tags['product_version']) ? Str::limit(trim((string) $tags['product_version']), 64, '') : null;
        $scope = in_array($tags['scope'] ?? null, ['vendor-specific', 'neutral'], true)
            ? $tags['scope']
            : (($vendor || $platform) ? 'vendor-specific' : null);

        return array_filter([
            'mdm_vendor' => $vendor,
            'data_platform' => $platform,
            'domain' => $domain,
            'scope' => $scope,
            'product' => $product ?: null,
            'product_version' => $version ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Map a per-file/URL tag payload (from a `meta` map) to the override keys buildOverrides expects. */
    public function coerceTags(array $t): array
    {
        return [
            'mdm_vendor' => $t['mdm_vendor'] ?? null,
            'data_platform' => $t['data_platform'] ?? null,
            'domain' => $t['domain'] ?? null,
            'product' => $t['product'] ?? null,
            'product_version' => $t['product_version'] ?? ($t['version'] ?? null),
            'scope' => $t['scope'] ?? null,
        ];
    }

    /** raw/<category|vendor>/<product-slug>/<version-slug>/ destination for a tag set. */
    public function destDir(string $root, array $overrides, ?string $category = null): string
    {
        $cat = preg_replace('/[^a-z0-9\-]/', '', strtolower($category ?? $overrides['mdm_vendor'] ?? 'uploads')) ?: 'uploads';
        $segments = [rtrim($root, '/'), 'raw', $cat];
        if (! empty($overrides['product'])) {
            $segments[] = Str::slug($overrides['product']);
        }
        if (! empty($overrides['product_version'])) {
            $segments[] = Str::slug($overrides['product_version']);
        }

        return implode('/', $segments);
    }

    /**
     * Persist approved new subjects (top-level + any per-file/URL tag sets) to taxonomy_terms before
     * validation, so the new domain validates and is reusable. Each tag set may carry `new_subject`
     * as ['value'=>..,'label'=>..] or a plain string.
     */
    public function persistNewSubjects(mixed $topLevel, array $taggedSets = []): void
    {
        $candidates = [];
        foreach ([$topLevel, ...array_map(fn ($t) => is_array($t) ? ($t['new_subject'] ?? null) : null, $taggedSets)] as $ns) {
            if (is_array($ns) && ! empty($ns['value'])) {
                $candidates[] = $ns;
            } elseif (is_string($ns) && $ns !== '') {
                $candidates[] = ['value' => $ns, 'label' => null];
            }
        }
        if (! $candidates) {
            return;
        }

        $changed = false;
        foreach ($candidates as $ns) {
            $slug = Str::slug((string) $ns['value']);
            if ($slug === '' || in_array($slug, Taxonomy::values('domain'), true)) {
                continue;
            }
            TaxonomyTerm::firstOrCreate(
                ['type' => 'domain', 'value' => $slug, 'vendor' => null],
                ['label' => $ns['label'] ?? null, 'sort_order' => 100, 'active' => true],
            );
            $changed = true;
        }
        if ($changed) {
            Taxonomy::flush();
        }
    }

    private function inList(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }
}
