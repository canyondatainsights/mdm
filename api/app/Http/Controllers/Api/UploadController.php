<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\IngestUploadedFile;
use App\Jobs\IngestUrlSource;
use App\Models\AuditLog;
use App\Models\Chunk;
use App\Models\Source;
use App\Models\TaxonomyTerm;
use App\Services\Kb\Classifier;
use App\Services\Kb\DocumentParser;
use App\Services\Taxonomy\Taxonomy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UploadController extends Controller
{
    /**
     * Scan & classify uploaded files BEFORE ingestion: extract a cheap excerpt and ask Claude to
     * suggest vendor/product/subject (proposing a new subject when none fits) for a human confirm
     * step. Does not move or ingest anything — the confirmed tags come back via store().
     */
    public function classify(Request $request, Classifier $classifier, DocumentParser $parser)
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Steward', 'Admin']),
            403,
            'Steward or Admin role required to expand the knowledge base.'
        );

        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'max:131072', 'mimes:pdf,md,markdown,txt'],
        ]);

        $out = [];
        foreach ($request->file('files') as $file) {
            $name = $file->getClientOriginalName();
            try {
                $suggestion = $classifier->classify($name, $parser->excerpt($file->getRealPath()));
            } catch (\Throwable $e) {
                $suggestion = [
                    'mdm_vendor' => null, 'data_platform' => null, 'product' => null, 'domain' => null,
                    'proposed_subject' => null, 'confidence' => 'low', 'reasoning' => null,
                    'error' => $e->getMessage(),
                ];
            }
            $out[] = ['filename' => $name, 'suggestion' => $suggestion];
        }

        return ['files' => $out];
    }

    /**
     * Upload one or more reference docs into kb/raw/<vendor>/<product>/<version>/ and queue
     * ingestion. Tags may be applied per file via a `meta` map (filename => tags) from the
     * classify step, or globally via top-level fields. Single-file `file` is still accepted.
     */
    public function store(Request $request)
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Steward', 'Admin']),
            403,
            'Steward or Admin role required to expand the knowledge base.'
        );

        // Per-file tags from the classify→confirm step, keyed by original filename.
        $perFile = [];
        if ($raw = $request->input('meta')) {
            $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $perFile = $decoded;
            }
        }

        // Persist any approved new subjects to the taxonomy FIRST, so they validate + are reusable.
        $this->persistNewSubjects($request->input('new_subject'), $perFile);

        $dims = Taxonomy::dimensions();
        $data = $request->validate([
            'files' => ['required_without_all:file,url', 'array'],
            'files.*' => ['file', 'max:131072', 'mimes:pdf,md,markdown,txt'],
            'file' => ['required_without_all:files,url', 'file', 'max:131072', 'mimes:pdf,md,markdown,txt'],
            'url' => ['required_without_all:files,file', 'url', 'max:2048'],
            'category' => ['nullable', 'string', 'max:64'],
            'mdm_vendor' => ['nullable', Rule::in($dims['mdm_vendor'])],
            'data_platform' => ['nullable', Rule::in($dims['data_platform'])],
            'domain' => ['nullable', Rule::in($dims['domain'])],
            'scope' => ['nullable', Rule::in(['vendor-specific', 'neutral'])],
            'product' => ['nullable', 'string', 'max:128'],
            'product_version' => ['nullable', 'string', 'max:64'],
        ]);

        $root = rtrim(config('mdm.kb_path'), '/');
        $category = $data['category'] ?? null;
        $global = $this->buildOverrides($data, $dims);

        $results = [];
        $uploaded = $request->file('files') ?? [];
        if ($single = $request->file('file')) {
            $uploaded[] = $single;
        }

        foreach ($uploaded as $original) {
            $origName = $original->getClientOriginalName();
            // Per-file tags win; fall back to the global tags otherwise.
            $overrides = isset($perFile[$origName]) && is_array($perFile[$origName])
                ? $this->buildOverrides($this->coerceTags($perFile[$origName]), $dims)
                : $global;

            $dir = $this->destDir($root, $overrides, $category);
            @mkdir($dir, 0775, true);

            $safe = Str::slug(pathinfo($origName, PATHINFO_FILENAME))
                .'.'.strtolower($original->getClientOriginalExtension());
            $original->move($dir, $safe);
            $abs = $dir.'/'.$safe;
            $rel = ltrim(str_replace($root, '', $abs), '/');

            Source::markQueued($rel, $overrides);
            IngestUploadedFile::dispatch($abs, $root, $overrides, $request->user()->id);
            $results[] = ['path' => $rel, 'status' => 'queued'];
        }

        // A reference URL is fetched, extracted to Markdown, and ingested like any source.
        if (! empty($data['url'])) {
            $dir = $this->destDir($root, $global, $category);
            @mkdir($dir, 0775, true);
            $host = parse_url($data['url'], PHP_URL_HOST) ?: 'link';
            $leaf = pathinfo(parse_url($data['url'], PHP_URL_PATH) ?? '', PATHINFO_FILENAME);
            $slug = Str::slug($host.'-'.$leaf) ?: 'reference-'.substr(md5($data['url']), 0, 8);
            $urlRel = ltrim(str_replace($root, '', $dir.'/'.$slug.'.md'), '/');

            Source::markQueued($urlRel, $global)->update(['doc_type' => 'URL', 'owner' => $data['url']]);
            IngestUrlSource::dispatch($data['url'], $urlRel, $root, $global, $request->user()->id);
            $results[] = ['path' => $urlRel, 'url' => $data['url'], 'status' => 'queued'];
        }

        AuditLog::record('source.uploaded', ['count' => count($results), 'per_file' => ! empty($perFile)]);

        return response()->json([
            'ok' => true,
            'queued' => count($results),
            'files' => $results,
        ], 201);
    }

    /** Poll ingestion status for the given source paths (for the upload progress UI). */
    public function status(Request $request)
    {
        $data = $request->validate([
            'paths' => ['required', 'array'],
            'paths.*' => ['string'],
        ]);

        $sources = Source::whereIn('path', $data['paths'])->get()->keyBy('path');

        $out = [];
        foreach ($data['paths'] as $p) {
            $s = $sources->get($p);
            $out[$p] = [
                'status' => $s->ingest_status ?? 'queued',
                'needs_metadata' => (bool) ($s->needs_metadata ?? false),
                'chunks' => $s ? Chunk::where('source_path', $p)->count() : 0,
            ];
        }

        return ['statuses' => $out];
    }

    /** Build ingestion overrides from a tag set, whitelisting dimension values against the taxonomy. */
    private function buildOverrides(array $tags, array $dims): array
    {
        $vendor = $this->inList($tags['mdm_vendor'] ?? null, $dims['mdm_vendor']);
        $platform = $this->inList($tags['data_platform'] ?? null, $dims['data_platform']);
        $domain = $this->inList($tags['domain'] ?? null, $dims['domain']);
        $product = isset($tags['product']) ? Str::limit(trim((string) $tags['product']), 128, '') : null;
        $version = isset($tags['product_version']) ? Str::limit(trim((string) $tags['product_version']), 64, '') : null;
        $scope = in_array($tags['scope'] ?? null, ['vendor-specific', 'neutral'], true)
            ? $tags['scope']
            : (($vendor || $platform) ? 'vendor-specific' : null);

        return array_filter([
            'mdm_vendor' => $vendor,
            'data_platform' => $platform,
            'domain' => $domain,
            'scope' => $scope,
            'product' => $product ?: null,
            'product_version' => $version ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Map a per-file tag payload (from `meta`) to the override keys buildOverrides expects. */
    private function coerceTags(array $t): array
    {
        return [
            'mdm_vendor' => $t['mdm_vendor'] ?? null,
            'data_platform' => $t['data_platform'] ?? null,
            'domain' => $t['domain'] ?? null,
            'product' => $t['product'] ?? null,
            'product_version' => $t['product_version'] ?? ($t['version'] ?? null),
            'scope' => $t['scope'] ?? null,
        ];
    }

    /** raw/<category|vendor>/<product-slug>/<version-slug>/ destination for a tag set. */
    private function destDir(string $root, array $overrides, ?string $category): string
    {
        $cat = preg_replace('/[^a-z0-9\-]/', '', strtolower($category ?? $overrides['mdm_vendor'] ?? 'uploads')) ?: 'uploads';
        $segments = [$root, 'raw', $cat];
        if (! empty($overrides['product'])) {
            $segments[] = Str::slug($overrides['product']);
        }
        if (! empty($overrides['product_version'])) {
            $segments[] = Str::slug($overrides['product_version']);
        }

        return implode('/', $segments);
    }

    /** Persist approved new subjects (top-level + per-file) to taxonomy_terms before validation. */
    private function persistNewSubjects(mixed $topLevel, array $perFile): void
    {
        $candidates = [];
        foreach ([$topLevel, ...array_map(fn ($t) => is_array($t) ? ($t['new_subject'] ?? null) : null, $perFile)] as $ns) {
            if (is_array($ns) && ! empty($ns['value'])) {
                $candidates[] = $ns;
            } elseif (is_string($ns) && $ns !== '') {
                $candidates[] = ['value' => $ns, 'label' => null];
            }
        }
        if (! $candidates) {
            return;
        }

        $changed = false;
        foreach ($candidates as $ns) {
            $slug = Str::slug((string) $ns['value']);
            if ($slug === '' || in_array($slug, Taxonomy::values('domain'), true)) {
                continue;
            }
            TaxonomyTerm::firstOrCreate(
                ['type' => 'domain', 'value' => $slug, 'vendor' => null],
                ['label' => $ns['label'] ?? null, 'sort_order' => 100, 'active' => true],
            );
            $changed = true;
        }
        if ($changed) {
            Taxonomy::flush();
        }
    }

    private function inList(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }
}
