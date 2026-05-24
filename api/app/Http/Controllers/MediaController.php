<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves wiki page media (architecture diagrams, etc.) from the versioned kb/wiki/_media tree.
 * Public + read-only: images aren't secrets, and <img> tags can't send auth headers. Streamed inline
 * with a long cache; path-traversal is rejected.
 */
class MediaController extends Controller
{
    public function wiki(string $path): BinaryFileResponse
    {
        $rel = ltrim($path, '/');
        abort_if($rel === '' || str_contains($rel, '..'), 404);

        $base = rtrim(config('mdm.kb_path'), '/').'/wiki/_media';
        $abs = $base.'/'.$rel;

        // Defence in depth: the resolved real path must stay inside the media root.
        $real = realpath($abs);
        abort_unless($real && str_starts_with($real, realpath($base) ?: $base) && is_file($real), 404);

        return response()->file($real, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
