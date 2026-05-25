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

        // Defence in depth: drop any invalid UTF-8 / NUL bytes that slipped through so json_encode
        // (which Guzzle runs on the request body) can't throw "Malformed UTF-8 characters".
        $clean = array_map(fn ($t) => self::scrub((string) $t), array_values($texts));

        $response = Http::timeout(120)
            ->post(rtrim($this->baseUrl, '/').'/embed', ['texts' => $clean]);

        if ($response->failed()) {
            throw new RuntimeException('Embedding sidecar request failed: '.$response->status().' '.$response->body());
        }

        return $response->json('embeddings', []);
    }

    /** Liveness probe for the admin health widget — true if the sidecar answers /health quickly. */
    public function health(): bool
    {
        try {
            return Http::timeout(3)->get(rtrim($this->baseUrl, '/').'/health')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private static function scrub(string $s): string
    {
        if ($s !== '' && ! mb_check_encoding($s, 'UTF-8')) {
            $s = function_exists('mb_scrub') ? mb_scrub($s, 'UTF-8') : (string) @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        }

        return str_replace("\0", '', $s);
    }
}
