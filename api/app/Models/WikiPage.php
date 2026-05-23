<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WikiPage extends Model
{
    protected $fillable = [
        'path', 'title', 'section', 'mdm_vendor', 'data_platform',
        'financial_model', 'domain', 'scope', 'page_updated_at', 'content_hash',
    ];

    protected $casts = [
        'page_updated_at' => 'datetime',
    ];
}
