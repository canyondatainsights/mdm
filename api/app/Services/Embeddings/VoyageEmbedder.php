<?php

namespace App\Services\Embeddings;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Hosted embeddings via the Voyage AI API (Anthropic-recommended). */
class VoyageEmbedder implements Embedder
{
    public function __construct(
        private string $apiKey,
        private string $model,
        private string $url,
        private int $dim,
    ) {}

    public function dimensions(): int
    {
        return $this->dim;
    }

    public function embed(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post($this->url, [
                'model' => $this->model,
                'input' => array_values($texts),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Voyage embeddings request failed: '.$response->status().' '.$response->body());
        }

        return array_map(fn ($row) => $row['embedding'], $response->json('data', []));
    }
}
