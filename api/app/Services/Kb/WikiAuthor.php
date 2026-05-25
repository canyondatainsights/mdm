<?php

namespace App\Services\Kb;

use App\Models\WikiPage;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Authors a wiki page directly: write kb/wiki/<section>/<slug>.md (front-matter + body + revision
 * log), ingest it into chunks, and git-commit. Shared by the Filament admin authoring flow and the
 * stewardship apply job (which reuses commit()). Wiki content lives on disk + in chunks; the
 * WikiPage row is metadata only, upserted by the Ingestor.
 */
class WikiAuthor
{
    public function __construct(private Ingestor $ingestor) {}

    /**
     * Create or overwrite a wiki page, ingest it, and commit. Returns the upserted WikiPage.
     * Pass $relPath to write to an existing page's path (edit) so a title change doesn't move the file.
     *
     * @param  array<string,?string>  $meta  mdm_vendor/data_platform/financial_model/domain/scope/product/product_version/extension
     */
    public function write(string $section, string $title, string $body, array $meta = [], ?string $author = null, ?string $email = null, ?string $relPath = null): WikiPage
    {
        $root = rtrim(config('mdm.kb_path'), '/');
        if ($relPath) {
            $rel = ltrim($relPath, '/');
        } else {
            $section = Str::slug(trim($section)) ?: '01-foundations';
            $slug = Str::slug($title) ?: ('page-'.substr(md5($title.microtime()), 0, 8));
            $rel = "wiki/{$section}/{$slug}.md";
        }
        $abs = $root.'/'.$rel;

        @mkdir(dirname($abs), 0755, true);
        file_put_contents($abs, $this->compose($title, $body, $meta));

        // Pass the chosen tags as overrides so they win over path/keyword derivation on the page + chunks.
        $this->ingestor->ingestFile($abs, 'wiki', $root, $this->overrides($meta));

        $this->commit($root, "wiki: author {$rel} — {$title}", $author, $email);

        return WikiPage::where('path', $rel)->firstOrFail();
    }

    /** Shared KB git commit, scoped to the kb directory. Returns the commit hash, or null on failure. */
    public function commit(string $kbRoot, string $message, ?string $author = null, ?string $email = null): ?string
    {
        $author = $author ?: 'system';
        $email = $email ?: 'system@mdm.local';

        $result = Process::path($kbRoot)
            ->run("git add -A -- . && git commit --author=\"{$author} <{$email}>\" -m ".escapeshellarg($message).' 2>&1');

        if ($result->successful()) {
            return trim(Process::path($kbRoot)->run('git rev-parse HEAD')->output());
        }

        return null;
    }

    /** Keep only non-empty tags as ingest overrides. @param array<string,?string> $meta */
    private function overrides(array $meta): array
    {
        $out = [];
        foreach (['mdm_vendor', 'data_platform', 'financial_model', 'domain', 'scope', 'product', 'product_version', 'extension', 'title'] as $k) {
            if (! empty($meta[$k])) {
                $out[$k] = $meta[$k];
            }
        }

        return $out;
    }

    /** Compose front-matter + body, ensuring a leading H1 and a revision-log table. */
    private function compose(string $title, string $body, array $meta): string
    {
        $fm = ['title' => $title];
        foreach (['mdm_vendor', 'data_platform', 'financial_model', 'domain', 'scope', 'product', 'product_version', 'extension'] as $k) {
            if (! empty($meta[$k])) {
                $fm[$k] = $meta[$k];
            }
        }

        $yaml = "---\n";
        foreach ($fm as $k => $v) {
            $yaml .= $k.': '.$this->yamlScalar((string) $v)."\n";
        }
        $yaml .= "---\n\n";

        $body = trim($body);
        if (! str_starts_with(ltrim($body), '#')) {
            $body = "# {$title}\n\n".$body;
        }
        if (stripos($body, '## Revision log') === false) {
            $body .= "\n\n## Revision log\n\n| Date | Change |\n|---|---|\n| ".now()->format('Y-m-d')." | Authored via admin. |\n";
        }

        return $yaml.$body."\n";
    }

    /** Quote a scalar only when it contains YAML-significant characters. */
    private function yamlScalar(string $v): string
    {
        return preg_match('/[:#\-{}\[\],&*?|<>=!%@`"]/', $v)
            ? '"'.str_replace('"', '\"', $v).'"'
            : $v;
    }
}
