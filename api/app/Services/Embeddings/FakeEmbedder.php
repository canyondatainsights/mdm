<?php

namespace App\Services\Embeddings;

/**
 * Deterministic, dependency-free embedder for offline development and tests.
 *
 * It hashes tokens into the vector space (a bag-of-words sketch) and L2-normalizes,
 * so cosine similarity tracks lexical overlap. This is NOT a semantic model — set
 * EMBEDDINGS_DRIVER=voyage (or sidecar) for real retrieval quality in production.
 */
class FakeEmbedder implements Embedder
{
    public function __construct(private int $dim = 1024) {}

    public function dimensions(): int
    {
        return $this->dim;
    }

    public function embed(array $texts): array
    {
        return array_map(fn (string $t) => $this->vector($t), $texts);
    }

    private function vector(string $text): array
    {
        $vec = array_fill(0, $this->dim, 0.0);
        $tokens = preg_split('/[^a-z0-9]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $tok) {
            // Two hashed slots per token reduce collisions.
            $h1 = crc32($tok) % $this->dim;
            $h2 = crc32('x'.$tok) % $this->dim;
            $vec[$h1] += 1.0;
            $vec[$h2] += 0.5;
        }

        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $vec)));
        if ($norm > 0) {
            foreach ($vec as $i => $v) {
                $vec[$i] = $v / $norm;
            }
        }

        return $vec;
    }
}
