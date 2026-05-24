<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single taxonomy term — one row of the reference-data table backing the lockable
 * dimensions (vendors/platforms/financial models/subjects) and per-vendor products.
 */
class TaxonomyTerm extends Model
{
    protected $fillable = ['type', 'value', 'label', 'vendor', 'sort_order', 'active'];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const TYPES = ['mdm_vendor', 'data_platform', 'financial_model', 'domain', 'product'];
}
