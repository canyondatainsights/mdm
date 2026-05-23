<?php

namespace App\Services\Embeddings;

interface Embedder
{
    /**
     * Embed one or more texts. Returns an array of float[] vectors, one per input,
     * each of length config('mdm.embeddings.dim').
     *
     * @param  string[]  $texts
     * @return array<int, array<int, float>>
     */
    public function embed(array $texts): array;

    public function dimensions(): int;
}
