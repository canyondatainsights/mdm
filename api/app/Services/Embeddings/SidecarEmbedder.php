<?php

namespace App\Services\Embeddings;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Local embeddings via the optional Python (sentence-transformers) sidecar. */
class SidecarEmbedder implements Embedder
{
    public function __construct(private string $baseUrl, private int $dim) {}

    public function dimensions(): int
    {
        return $this->dim;
    }

    public function embed(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $response = Http::timeout(120)
            ->post(rtrim($this->baseUrl, '/').'/embed', ['texts' => array_values($texts)]);

        if ($response->failed()) {
            throw new RuntimeException('Embedding sidecar request failed: '.$response->status().' '.$response->body());
        }

        return $response->json('embeddings', []);
    }
}
