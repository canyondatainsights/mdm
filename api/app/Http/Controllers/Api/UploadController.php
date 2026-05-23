<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Kb\Ingestor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UploadController extends Controller
{
    public function __construct(private Ingestor $ingestor) {}

    /** Upload reference material into kb/raw/<category>/ and ingest it. */
    public function store(Request $request)
    {
        $dims = config('mdm.dimensions');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:pdf,md,markdown,txt'],
            'category' => ['nullable', 'string', 'max:64'],
            'mdm_vendor' => ['nullable', Rule::in($dims['mdm_vendor'])],
            'data_platform' => ['nullable', Rule::in($dims['data_platform'])],
            'domain' => ['nullable', Rule::in($dims['domain'])],
        ]);

        $root = rtrim(config('mdm.kb_path'), '/');
        $category = preg_replace('/[^a-z0-9\-]/', '', strtolower($data['category'] ?? 'uploads')) ?: 'uploads';
        $dir = $root.'/raw/'.$category;
        @mkdir($dir, 0775, true);

        $original = $request->file('file');
        $safe = Str::slug(pathinfo($original->getClientOriginalName(), PATHINFO_FILENAME))
            .'.'.strtolower($original->getClientOriginalExtension());
        $abs = $dir.'/'.$safe;
        $original->move($dir, $safe);

        $result = $this->ingestor->ingestFile($abs, 'raw', $root);

        AuditLog::record('source.uploaded', ['path' => $result['path'], 'chunks' => $result['chunks']]);

        return response()->json([
            'ok' => true,
            'path' => $result['path'],
            'status' => $result['status'],
            'chunks' => $result['chunks'],
        ], 201);
    }
}
