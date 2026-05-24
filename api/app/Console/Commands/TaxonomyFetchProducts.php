<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\TaxonomyTerm;
use App\Services\SettingsService;
use App\Services\Taxonomy\Taxonomy;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

/**
 * Fetch a vendor's product line via Claude and upsert them as taxonomy `product` terms — so a new
 * vendor's catalog can be populated on demand without hand-maintaining a list. Idempotent; also
 * ensures the vendor exists as an mdm_vendor term. e.g. `taxonomy:fetch-products databricks`.
 */
class TaxonomyFetchProducts extends Command
{
    protected $signature = 'taxonomy:fetch-products {vendor : vendor slug or name} {--dry-run : show results without writing}';

    protected $description = "Fetch a vendor's product line via Claude and upsert them as taxonomy product terms.";

    public function handle(SettingsService $settings): int
    {
        $vendor = Str::slug($this->argument('vendor'));
        if ($vendor === '') {
            $this->error('Provide a vendor.');

            return self::FAILURE;
        }

        $key = $settings->anthropicKey();
        if (! $key) {
            $this->error('No Claude API key configured (set it in Settings).');

            return self::FAILURE;
        }

        $system = 'You are a product-catalog assistant. Given a software vendor, list its principal current '
            .'commercial products/services by their proper product names (not features or editions). '
            .'Respond with a single minified JSON array of strings and nothing else.';
        $prompt = "Vendor: {$vendor}\nReturn a JSON array of the vendor's well-known current products/services "
            ."(roughly 8-20). Output ONLY the JSON array.";

        try {
            $resp = Prism::text()
                ->using(Provider::Anthropic, config('mdm.anthropic.model'), ['api_key' => $key])
                ->withMaxTokens(700)
                ->withSystemPrompt($system)
                ->withPrompt($prompt)
                ->asText();
        } catch (\Throwable $e) {
            $this->error('Fetch failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $products = $this->parseList($resp->text);
        if (! $products) {
            $this->error('No products parsed from the model response.');

            return self::FAILURE;
        }

        $this->info("Products for {$vendor} (".count($products)."): ".implode(', ', $products));
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        // Ensure the vendor itself is a selectable mdm_vendor term.
        if (! TaxonomyTerm::where('type', 'mdm_vendor')->where('value', $vendor)->whereNull('vendor')->exists()) {
            TaxonomyTerm::create(['type' => 'mdm_vendor', 'value' => $vendor, 'label' => Str::title($vendor), 'sort_order' => 50, 'active' => true]);
        }

        $i = 0;
        $added = 0;
        foreach ($products as $p) {
            $term = TaxonomyTerm::firstOrCreate(
                ['type' => 'product', 'value' => $p, 'vendor' => $vendor],
                ['active' => true, 'sort_order' => $i++],
            );
            $added += $term->wasRecentlyCreated ? 1 : 0;
        }
        Taxonomy::flush();
        AuditLog::record('taxonomy.products_fetched', ['vendor' => $vendor, 'count' => count($products), 'added' => $added]);

        $this->info("Upserted ".count($products)." products ({$added} new) for {$vendor}.");

        return self::SUCCESS;
    }

    /** Parse + sanitize a JSON array of product names (dedupe case-insensitively, keep first casing). */
    private function parseList(string $raw): array
    {
        $json = trim($raw);
        if (preg_match('/\[.*\]/s', $json, $m)) {
            $json = $m[0];
        }
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $p) {
            $name = is_string($p) ? trim($p) : (is_array($p) ? trim((string) ($p['name'] ?? '')) : '');
            if ($name !== '' && mb_strlen($name) <= 128) {
                $out[mb_strtolower($name)] ??= $name;
            }
        }

        return array_values($out);
    }
}
