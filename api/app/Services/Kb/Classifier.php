<?php

namespace App\Services\Kb;

use App\Services\SettingsService;
use App\Services\Taxonomy\Taxonomy;
use Illuminate\Support\Str;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

/**
 * Pre-ingest classifier: given a document's filename + a short excerpt, asks Claude to map it
 * onto the controlled taxonomy (vendor / data platform / product / subject). Returns suggestions
 * for a human confirm step — it never ingests. When a doc fits no existing subject, the model may
 * propose a new one (which the upload flow can persist to the taxonomy on approval).
 */
class Classifier
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @return array{mdm_vendor:?string, data_platform:?string, product:?string, domain:?string,
     *               extension:?string, proposed_subject:?array{value:string,label:string},
     *               confidence:string, reasoning:?string}
     */
    public function classify(string $filename, string $excerpt): array
    {
        $key = $this->settings->anthropicKey();
        if (! $key) {
            throw new \RuntimeException('No Claude API key configured.');
        }

        $vendors = Taxonomy::values('mdm_vendor');
        $platforms = Taxonomy::values('data_platform');
        $domains = Taxonomy::values('domain');
        $extensions = Taxonomy::values('extension');
        $products = Taxonomy::products();

        $productLines = [];
        foreach ($products as $vendor => $list) {
            $productLines[] = "  - {$vendor}: ".implode(', ', $list);
        }

        $system = 'You are a metadata classifier for an MDM (master data management) knowledge base. '
            .'Given a document filename and excerpt, tag it precisely using ONLY the controlled vocabularies '
            .'provided. Be specific and conservative: prefer null over guessing, and never invent vendors or '
            .'products. Respond with a single minified JSON object and nothing else.';

        $vocab = "Vendors: ".implode(', ', $vendors)."\n"
            ."Data platforms: ".implode(', ', $platforms)."\n"
            ."Subjects/domains: ".implode(', ', $domains)."\n"
            ."Extensions/verticals (industry or add-on editions): ".implode(', ', $extensions)."\n"
            ."Products by vendor:\n".implode("\n", $productLines);

        $prompt = <<<TXT
        {$vocab}

        Filename: {$filename}
        Excerpt:
        ---
        {$excerpt}
        ---

        Classify the document. Rules:
        - "mdm_vendor": the vendor it documents, or null.
        - "data_platform": only if the doc is genuinely ABOUT that platform, not an incidental mention.
        - "product": the MOST SPECIFIC core product (e.g. prefer "Customer 360" or "Supplier 360" over the
          generic "MDM Hub"). Choose from the chosen vendor's products; exact string, or null.
        - "domain": the PRIMARY business domain the doc is about. A supplier doc is "supplier" (NOT "customer");
          a doc spanning many domains (a platform/integration/multi-domain doc) is "general". Do NOT pick
          "customer" just because the filename or product name contains the word "customer".
        - "extension": if the doc is an INDUSTRY VERTICAL or ADD-ON edition (e.g. "for Insurance"→insurance,
          "for Retail"→retail, Microsoft Fabric→fabric, "for SAP"→sap, ESG→esg, a consent add-on→consent),
          return that extension value. If it is the CORE product (no vertical/add-on), return null.
        - "proposed_subject": null, OR {"value":"<kebab-slug>","label":"<Title>"} ONLY if it clearly belongs to
          a subject NOT in the list above.
        - "confidence": "high" | "medium" | "low".
        - "reasoning": one short sentence.
        Output ONLY the JSON object.
        TXT;

        $response = Prism::text()
            ->using(Provider::Anthropic, config('mdm.anthropic.model'), ['api_key' => $key])
            ->withMaxTokens(500)
            ->withSystemPrompt($system)
            ->withPrompt($prompt)
            ->asText();

        return $this->normalize($response->text, $vendors, $platforms, $domains, $extensions, $products);
    }

    /** Parse + whitelist the model's JSON so only valid taxonomy values survive. */
    private function normalize(string $raw, array $vendors, array $platforms, array $domains, array $extensions, array $products): array
    {
        $json = trim($raw);
        if (preg_match('/\{.*\}/s', $json, $m)) {
            $json = $m[0];
        }
        $data = json_decode($json, true) ?: [];

        $vendor = $this->pick($data['mdm_vendor'] ?? null, $vendors);
        $platform = $this->pick($data['data_platform'] ?? null, $platforms);
        $domain = $this->pick($data['domain'] ?? null, $domains);
        $extension = $this->pick($data['extension'] ?? null, $extensions);

        $vendorProducts = $vendor && ! empty($products[$vendor])
            ? $products[$vendor]
            : ($products ? array_merge([], ...array_values($products)) : []);
        $product = $this->pick($data['product'] ?? null, $vendorProducts);

        $proposed = null;
        $ps = $data['proposed_subject'] ?? null;
        if (is_array($ps) && ! empty($ps['value'])) {
            $slug = Str::slug((string) $ps['value']);
            if ($slug !== '' && ! in_array($slug, $domains, true)) {
                $proposed = ['value' => $slug, 'label' => (string) ($ps['label'] ?? $ps['value'])];
            }
        }

        $confidence = in_array($data['confidence'] ?? null, ['high', 'medium', 'low'], true)
            ? $data['confidence'] : 'low';

        return [
            'mdm_vendor' => $vendor,
            'data_platform' => $platform,
            'product' => $product,
            'domain' => $domain,
            'extension' => $extension,
            'proposed_subject' => $proposed,
            'confidence' => $confidence,
            'reasoning' => isset($data['reasoning']) ? (string) $data['reasoning'] : null,
        ];
    }

    /** Return the value only if it matches the allowed list (case-insensitive), preserving canonical casing. */
    private function pick(mixed $value, array $allowed): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        foreach ($allowed as $a) {
            if (strcasecmp((string) $a, $value) === 0) {
                return (string) $a;
            }
        }

        return null;
    }
}
