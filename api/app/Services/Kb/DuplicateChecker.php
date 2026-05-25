<?php

namespace App\Services\Kb;

use App\Models\Source;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Pre-import duplicate detection. A file is flagged a duplicate if its bytes match an existing
 * source's content_hash (strongest — catches renamed re-uploads), or its slugified filename already
 * exists in the KB (an active `sources.path` row or a file in the kb/raw tree). Lets the upload flow
 * warn/skip before re-importing the same document, instead of relying only on the post-ingest
 * `superseded` cleanup.
 */
class DuplicateChecker
{
    /** Memoized lowercase basenames found under kb/raw (scanned once per instance). */
    private ?array $rawNames = null;

    /**
     * @return array{duplicate:bool, by:?string, existing:?array{id:int,title:?string,path:string}}
     */
    public function check(UploadedFile $file): array
    {
        return $this->checkFile($file->getRealPath(), $file->getClientOriginalName());
    }

    /**
     * Core check by absolute path + original filename (usable from queued jobs that hold a staged file
     * rather than an UploadedFile).
     *
     * @return array{duplicate:bool, by:?string, existing:?array{id:int,title:?string,path:string}}
     */
    public function checkFile(string $absPath, string $originalName): array
    {
        // 1. Exact-content match — same bytes already ingested, even under a different name.
        $hash = @md5_file($absPath);
        if ($hash && $existing = Source::where('content_hash', $hash)->where('superseded', false)->first()) {
            return $this->hit('content', $existing);
        }

        // 2. Same slugified filename already an active source.
        $slug = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)).'.'.strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($existing = Source::whereRaw('lower(path) like ?', ['%/'.strtolower($slug)])->where('superseded', false)->first()) {
            return $this->hit('filename', $existing);
        }

        // 3. Same filename sitting in kb/raw (covers files on disk not yet/again in `sources`).
        if ($this->existsInRaw($slug)) {
            return ['duplicate' => true, 'by' => 'filename', 'existing' => ['id' => 0, 'title' => null, 'path' => 'raw/…/'.$slug]];
        }

        return ['duplicate' => false, 'by' => null, 'existing' => null];
    }

    private function hit(string $by, Source $s): array
    {
        return ['duplicate' => true, 'by' => $by, 'existing' => ['id' => $s->id, 'title' => $s->title, 'path' => $s->path]];
    }

    private function existsInRaw(string $slug): bool
    {
        $this->rawNames ??= $this->scanRawNames();

        return isset($this->rawNames[strtolower($slug)]);
    }

    /** @return array<string,true> lowercase basename => true */
    private function scanRawNames(): array
    {
        $raw = rtrim((string) config('mdm.kb_path'), '/').'/raw';
        if (! is_dir($raw)) {
            return [];
        }

        $names = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raw, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $names[strtolower($f->getFilename())] = true;
            }
        }

        return $names;
    }
}
