<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class SettingsService
{
    public const ANTHROPIC_KEY = 'anthropic_api_key';

    public const ANTHROPIC_MODEL = 'anthropic_model';

    /** Selectable Claude models (id => human label). The first is the recommended default. */
    public const MODELS = [
        'claude-opus-4-7' => 'Claude Opus 4.7 — most capable',
        'claude-sonnet-4-6' => 'Claude Sonnet 4.6 — balanced (default)',
        'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 — fastest',
    ];

    /** Resolution order: DB setting (admin UI) first, then the env value. */
    public function anthropicKey(): ?string
    {
        return Setting::get(self::ANTHROPIC_KEY) ?: config('mdm.anthropic.env_key');
    }

    /** The active Claude model: DB setting (admin UI) first, then config/env default. */
    public function anthropicModel(): string
    {
        return Setting::get(self::ANTHROPIC_MODEL) ?: config('mdm.anthropic.model');
    }

    public function setAnthropicModel(?string $model): void
    {
        Setting::put(self::ANTHROPIC_MODEL, $model ?: null);
    }

    public function hasAnthropicKey(): bool
    {
        return filled($this->anthropicKey());
    }

    public function source(): ?string
    {
        if (filled(Setting::get(self::ANTHROPIC_KEY))) {
            return 'config';
        }

        return filled(config('mdm.anthropic.env_key')) ? 'env' : null;
    }

    /** A non-reversible hint for the UI, e.g. "sk-…a1b2". Never returns the full key. */
    public function maskedHint(): ?string
    {
        $key = $this->anthropicKey();
        if (! $key) {
            return null;
        }

        return 'sk-…'.substr($key, -4);
    }

    public function setAnthropicKey(?string $key): void
    {
        Setting::put(self::ANTHROPIC_KEY, $key ?: null);
    }

    /** Validate a key against the Anthropic API with a minimal request. */
    public function testAnthropicKey(?string $key = null): array
    {
        $key = $key ?: $this->anthropicKey();
        if (! $key) {
            return ['ok' => false, 'message' => 'No API key set.'];
        }

        try {
            $resp = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(20)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->anthropicModel(),
                'max_tokens' => 1,
                'messages' => [['role' => 'user', 'content' => 'ping']],
            ]);

            if ($resp->successful()) {
                return ['ok' => true, 'message' => 'Key is valid.'];
            }

            $err = $resp->json('error.message') ?? ('HTTP '.$resp->status());

            return ['ok' => false, 'message' => $err];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
