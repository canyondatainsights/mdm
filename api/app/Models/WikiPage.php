<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WikiPage extends Model
{
    protected $fillable = [
        'path', 'title', 'section', 'sort_order', 'mdm_vendor', 'data_platform',
        'financial_model', 'domain', 'scope', 'product', 'product_version',
        'page_updated_at', 'content_hash',
    ];

    protected $casts = [
        'page_updated_at' => 'datetime',
        'sort_order' => 'integer',
    ];
}
