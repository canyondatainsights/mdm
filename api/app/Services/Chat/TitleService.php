<?php

namespace App\Services\Chat;

use App\Services\SettingsService;
use Illuminate\Support\Str;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

/**
 * Generates a short, specific conversation title from its first question (+ answer) via Claude,
 * falling back to a cleaned/truncated question when there's no key or the call fails.
 */
class TitleService
{
    public function __construct(private SettingsService $settings) {}

    public function generate(string $question, ?string $answer = null): string
    {
        $fallback = $this->fromQuestion($question);
        $key = $this->settings->anthropicKey();
        if (! $key) {
            return $fallback;
        }

        try {
            $prompt = "First question:\n".Str::limit(trim($question), 500);
            if (filled($answer)) {
                $prompt .= "\n\nAssistant answer (excerpt):\n".Str::limit(strip_tags($answer), 600);
            }

            $resp = Prism::text()
                ->using(Provider::Anthropic, $this->settings->anthropicModel(), ['api_key' => $key])
                ->withClientOptions(['timeout' => 30])
                ->withMaxTokens(24)
                ->withSystemPrompt('You write very short, specific titles for a master-data-management / data-governance knowledge assistant. Reply with ONLY the title: 3–6 words, Title Case, no quotes, no trailing punctuation. Name the concrete subject (vendor / platform / topic) — never generic words like "question", "help", or "conversation".')
                ->withPrompt($prompt)
                ->asText();

            $title = $this->clean($resp->text);

            return $title !== '' ? $title : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function fromQuestion(string $q): string
    {
        $q = trim(preg_replace('/\s+/', ' ', strip_tags($q)) ?? '');

        return $q === '' ? 'New conversation' : Str::limit($q, 60, '…');
    }

    private function clean(string $t): string
    {
        $t = trim(preg_replace('/\s+/', ' ', strip_tags($t)) ?? '');
        $t = trim($t, " \t\n\r\0\x0B\"'.");

        return Str::limit($t, 70, '');
    }
}
