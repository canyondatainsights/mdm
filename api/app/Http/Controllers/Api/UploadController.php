<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\IngestUploadedFile;
use App\Jobs\IngestUrlSource;
use App\Models\AuditLog;
use App\Models\Chunk;
use App\Models\Source;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UploadController extends Controller
{
    /**
     * Upload one or more reference docs into kb/raw/<vendor>/<product>/<version>/ and
     * queue ingestion. Tagged by vendor + product + version so the KB can be expanded
     * and retrieved per product/version. Single-file `file` is still accepted.
     */
    public function store(Request $request)
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Steward', 'Admin']),
            403,
            'Steward or Admin role required to expand the knowledge base.'
        );

        $dims = \App\Services\Taxonomy\Taxonomy::dimensions();

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
        $vendor = $data['mdm_vendor'] ?? null;
        $platform = $data['data_platform'] ?? null;
        $product = $data['product'] ?? null;
        $version = $data['product_version'] ?? null;

        // A doc tagged to a vendor/platform is vendor-specific unless told otherwise.
        $scope = $data['scope'] ?? (($vendor || $platform) ? 'vendor-specific' : null);

        // Destination: raw/<category|vendor>/<product-slug>/<version-slug>/
        $category = preg_replace('/[^a-z0-9\-]/', '', strtolower($data['category'] ?? $vendor ?? 'uploads')) ?: 'uploads';
        $segments = [$root, 'raw', $category];
        if ($product) {
            $segments[] = Str::slug($product);
        }
        if ($version) {
            $segments[] = Str::slug($version);
        }
        $dir = implode('/', $segments);
        @mkdir($dir, 0775, true);

        $overrides = array_filter([
            'mdm_vendor' => $vendor,
            'data_platform' => $platform,
            'domain' => $data['domain'] ?? null,
            'scope' => $scope,
            'product' => $product,
            'product_version' => $version,
        ], fn ($v) => $v !== null && $v !== '');

        // Normalize single `file` and multiple `files[]` into one list.
        $uploaded = $request->file('files') ?? [];
        if ($single = $request->file('file')) {
            $uploaded[] = $single;
        }

        $results = [];
        foreach ($uploaded as $original) {
            $safe = Str::slug(pathinfo($original->getClientOriginalName(), PATHINFO_FILENAME))
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
            $host = parse_url($data['url'], PHP_URL_HOST) ?: 'link';
            $leaf = pathinfo(parse_url($data['url'], PHP_URL_PATH) ?? '', PATHINFO_FILENAME);
            $slug = Str::slug($host.'-'.$leaf) ?: 'reference-'.substr(md5($data['url']), 0, 8);
            $urlRel = ltrim(str_replace($root, '', $dir.'/'.$slug.'.md'), '/');

            Source::markQueued($urlRel, $overrides)->update(['doc_type' => 'URL', 'owner' => $data['url']]);
            IngestUrlSource::dispatch($data['url'], $urlRel, $root, $overrides, $request->user()->id);
            $results[] = ['path' => $urlRel, 'url' => $data['url'], 'status' => 'queued'];
        }

        AuditLog::record('source.uploaded', ['count' => count($results), 'overrides' => $overrides]);

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
}
