<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\IngestUploadedFile;
use App\Jobs\IngestUrlSource;
use App\Models\AuditLog;
use App\Models\Chunk;
use App\Models\Source;
use App\Services\Kb\Classifier;
use App\Services\Kb\DocumentParser;
use App\Services\Kb\UploadTagger;
use App\Services\Kb\UrlFetcher;
use App\Services\Taxonomy\Taxonomy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UploadController extends Controller
{
    /**
     * Scan & classify uploaded files and/or a reference URL BEFORE ingestion: extract a cheap
     * excerpt and ask Claude to suggest vendor/product/subject (proposing a new subject when none
     * fits) for a human confirm step. Ingests nothing — confirmed tags come back via store().
     */
    public function classify(Request $request, Classifier $classifier, DocumentParser $parser, UrlFetcher $urlFetcher)
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Steward', 'Admin']),
            403,
            'Steward or Admin role required to expand the knowledge base.'
        );

        $request->validate([
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:131072', 'mimes:pdf,md,markdown,txt'],
            'url' => ['nullable', 'url', 'max:2048'],
        ]);

        $out = [];
        foreach ($request->file('files') ?? [] as $file) {
            $name = $file->getClientOriginalName();
            try {
                $suggestion = $classifier->classify($name, $parser->excerpt($file->getRealPath()));
            } catch (\Throwable $e) {
                $suggestion = $this->emptySuggestion($e->getMessage());
            }
            $out[] = ['filename' => $name, 'suggestion' => $suggestion];
        }

        if ($url = $request->input('url')) {
            try {
                $excerpt = $urlFetcher->excerpt($url);
                $suggestion = $excerpt === ''
                    ? $this->emptySuggestion('Could not fetch readable text from the URL.')
                    : $classifier->classify($url, $excerpt);
            } catch (\Throwable $e) {
                $suggestion = $this->emptySuggestion($e->getMessage());
            }
            $out[] = ['filename' => $url, 'suggestion' => $suggestion, 'is_url' => true];
        }

        abort_if(empty($out), 422, 'Add at least one file or URL to classify.');

        return ['files' => $out];
    }

    /**
     * Upload one or more reference docs into kb/raw/<vendor>/<product>/<version>/ and queue
     * ingestion. Tags may be applied per file via a `meta` map (filename => tags) and to the URL
     * via `url_meta` (both from the classify step), or globally via top-level fields.
     */
    public function store(Request $request, UploadTagger $tagger)
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Steward', 'Admin']),
            403,
            'Steward or Admin role required to expand the knowledge base.'
        );

        $perFile = $this->decodeMeta($request->input('meta'));      // filename => tags
        $urlMeta = $this->decodeMeta($request->input('url_meta')) ?: null;

        // Persist any approved new subjects (top-level + per-file + URL) FIRST so domains validate.
        $tagger->persistNewSubjects(
            $request->input('new_subject'),
            array_merge(array_values($perFile), $urlMeta ? [$urlMeta] : []),
        );

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
        $global = $tagger->buildOverrides($data, $dims);

        $results = [];
        $uploaded = $request->file('files') ?? [];
        if ($single = $request->file('file')) {
            $uploaded[] = $single;
        }

        foreach ($uploaded as $original) {
            $origName = $original->getClientOriginalName();
            $overrides = isset($perFile[$origName]) && is_array($perFile[$origName])
                ? $tagger->buildOverrides($tagger->coerceTags($perFile[$origName]), $dims)
                : $global;

            $dir = $tagger->destDir($root, $overrides, $category);
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
            $overrides = $urlMeta ? $tagger->buildOverrides($tagger->coerceTags($urlMeta), $dims) : $global;
            $dir = $tagger->destDir($root, $overrides, $category);
            @mkdir($dir, 0775, true);
            $host = parse_url($data['url'], PHP_URL_HOST) ?: 'link';
            $leaf = pathinfo(parse_url($data['url'], PHP_URL_PATH) ?? '', PATHINFO_FILENAME);
            $slug = Str::slug($host.'-'.$leaf) ?: 'reference-'.substr(md5($data['url']), 0, 8);
            $urlRel = ltrim(str_replace($root, '', $dir.'/'.$slug.'.md'), '/');

            Source::markQueued($urlRel, $overrides)->update(['doc_type' => 'URL', 'owner' => $data['url']]);
            IngestUrlSource::dispatch($data['url'], $urlRel, $root, $overrides, $request->user()->id);
            $results[] = ['path' => $urlRel, 'url' => $data['url'], 'status' => 'queued'];
        }

        AuditLog::record('source.uploaded', ['count' => count($results), 'per_file' => ! empty($perFile) || (bool) $urlMeta]);

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
                'approved' => (bool) ($s->approved ?? false),
                'chunks' => $s ? Chunk::where('source_path', $p)->count() : 0,
            ];
        }

        return ['statuses' => $out];
    }

    /** Decode a JSON (or already-array) `meta`/`url_meta` payload to an array. */
    private function decodeMeta(mixed $raw): array
    {
        if (! $raw) {
            return [];
        }
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** A null classification suggestion (used when classify fails for a file/URL). */
    private function emptySuggestion(?string $error = null): array
    {
        return [
            'mdm_vendor' => null, 'data_platform' => null, 'product' => null, 'domain' => null,
            'proposed_subject' => null, 'confidence' => 'low', 'reasoning' => null, 'error' => $error,
        ];
    }
}
