<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Source extends Model
{
    protected $fillable = [
        'title', 'doc_type', 'path', 'owner', 'pages', 'tags',
        'mdm_vendor', 'data_platform', 'financial_model',
        'domain', 'scope', 'product', 'product_version', 'approved', 'needs_metadata', 'uploaded_by',
        'ingest_status', 'content_hash', 'superseded',
    ];

    protected $casts = [
        'tags' => 'array',
        'approved' => 'boolean',
        'needs_metadata' => 'boolean',
        'superseded' => 'boolean',
        'pages' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Register/refresh a source row in 'queued' state so the UI can track ingestion progress. */
    public static function markQueued(string $path, array $overrides = []): self
    {
        $s = static::firstOrNew(['path' => $path]);
        if (! $s->exists) {
            $s->title = Str::headline(pathinfo($path, PATHINFO_FILENAME));
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $s->doc_type = match ($ext) {
                'pdf' => 'PDF',
                'md', 'markdown' => 'MD',
                'txt' => 'TXT',
                default => strtoupper($ext) ?: 'DOC',
            };
            // New uploads land pending — a steward approves them before they're used in answers.
            $s->approved = false;
        }
        foreach (['mdm_vendor', 'data_platform', 'domain', 'scope', 'product', 'product_version'] as $k) {
            if (! empty($overrides[$k])) {
                $s->{$k} = $overrides[$k];
            }
        }
        $s->ingest_status = 'queued';
        $s->superseded = false;
        $s->save();

        return $s;
    }
}
