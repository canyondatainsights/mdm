<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Services\SettingsService;
use App\Services\Taxonomy\Taxonomy;
use Illuminate\Support\Str;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

/**
 * Generates up to 3 suggested questions for a conversation — stack-aware STARTERS (no prior turn)
 * or contextual FOLLOW-UPS (given the last Q&A). LLM-generated within the locked stack, with a
 * deterministic taxonomy/stack template as a best-effort fallback (no key / rate-limited / error).
 */
class SuggestionService
{
    public function __construct(private SettingsService $settings) {}

    /** @return array<int,string> */
    public function suggest(Conversation $conversation, ?string $lastUser = null, ?string $lastAnswer = null): array
    {
        try {
            $q = $this->viaLlm($conversation, $lastUser, $lastAnswer);
            if ($q) {
                return $q;
            }
        } catch (\Throwable) {
            // fall through to the deterministic template
        }

        return $this->template($conversation);
    }

    private function viaLlm(Conversation $c, ?string $lastUser, ?string $lastAnswer): array
    {
        $key = $this->settings->anthropicKey();
        if (! $key) {
            throw new \RuntimeException('No Claude API key configured.');
        }

        $stack = $c->lockedStack();
        $vendor = $stack['mdm_vendor'] ?? 'unspecified';
        $platform = $stack['data_platform'] ?? 'none';
        $domains = implode(', ', $stack['domains'] ?: ['general']);
        $exts = ! empty($stack['extensions']) ? implode(', ', $stack['extensions']) : 'none';

        $products = array_slice(Taxonomy::productsFor($vendor !== 'unspecified' ? $vendor : '_'), 0, 10);
        if ($platform !== 'none') {
            $products = array_merge($products, array_slice(Taxonomy::productsFor($platform), 0, 10));
        }
        $productLine = $products ? "\n  Relevant products: ".implode(', ', array_unique($products)) : '';

        $system = 'You generate short, specific follow-up questions a data/MDM practitioner would ask, '
            .'STRICTLY within the locked technology stack and subject. Each must be answerable from that '
            .'vendor/platform/domain — never introduce other vendors. Respond with a single minified JSON '
            .'array of exactly 3 question strings and nothing else.';

        $prompt = "Locked stack:\n  MDM vendor: {$vendor}\n  Data platform: {$platform}\n"
            ."  Domain/subject: {$domains}\n  Extensions: {$exts}{$productLine}\n";
        if ($lastUser) {
            $prompt .= "\nThe user just asked: \"".Str::limit($lastUser, 300)."\"\n";
            if ($lastAnswer) {
                $prompt .= "Assistant answer (summary): \"".Str::limit(strip_tags($lastAnswer), 500)."\"\n";
            }
            $prompt .= "\nReturn 3 natural FOLLOW-UP questions that go deeper, specific to this stack and subject.";
        } else {
            $prompt .= "\nReturn 3 good STARTER questions to explore this stack — specific to the vendor/platform and domain.";
        }

        $resp = Prism::text()
            ->using(Provider::Anthropic, config('mdm.anthropic.model'), ['api_key' => $key])
            ->withMaxTokens(300)
            ->withSystemPrompt($system)
            ->withPrompt($prompt)
            ->asText();

        return $this->parse($resp->text);
    }

    /** @return array<int,string> */
    private function parse(string $raw): array
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
        foreach ($data as $q) {
            $q = is_string($q) ? trim($q) : '';
            if ($q !== '' && mb_strlen($q) <= 160) {
                $out[] = $q;
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 3);
    }

    /** Deterministic stack/subject-aware fallback. @return array<int,string> */
    private function template(Conversation $c): array
    {
        $stack = $c->lockedStack();
        $vendor = $stack['mdm_vendor'];
        $platform = $stack['data_platform'];
        $tech = $vendor ? Str::title($vendor) : ($platform ? Str::title($platform) : 'this stack');
        $subject = str_replace('-', ' ', ($stack['domains'] ?: ['general'])[0]);

        return [
            "What are the {$subject} capabilities in {$tech}?",
            $platform
                ? 'How do I cleanse, validate and tag PII data in '.Str::title($platform).'?'
                : "How do I cleanse, validate and tag sensitive data with {$tech}?",
            "What does the knowledge base cover about {$subject} for {$tech}?",
        ];
    }
}
