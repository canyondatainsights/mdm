<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;

class Chunk extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'source_kind', 'source_path', 'wiki_page_id', 'source_id', 'anchor',
        'chunk_index', 'content', 'token_count', 'content_hash',
        'mdm_vendor', 'data_platform', 'financial_model', 'domain', 'scope',
        'product', 'product_version', 'embedding',
    ];

    protected $casts = [
        'embedding' => Vector::class,
    ];
}
