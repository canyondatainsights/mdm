<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Source extends Model
{
    protected $fillable = [
        'title', 'doc_type', 'path', 'owner', 'pages', 'tags',
        'mdm_vendor', 'data_platform', 'financial_model',
        'domain', 'scope', 'approved', 'uploaded_by',
    ];

    protected $casts = [
        'tags' => 'array',
        'approved' => 'boolean',
        'pages' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
