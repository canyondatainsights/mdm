<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A documentation crawler profile (sitemaps + exclude patterns + a section→{product,domain,match}
 * map). Managed in the Filament admin and run ad-hoc; `CrawlerService` consumes `toProfile()`.
 */
class Crawler extends Model
{
    protected $fillable = ['key', 'name', 'platform', 'sitemaps', 'exclude', 'sections', 'active', 'sort_order', 'notes'];

    protected $casts = [
        'sitemaps' => 'array',
        'exclude' => 'array',
        'sections' => 'array',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return array{platform:string, sitemaps:array, exclude:array, sections:array} */
    public function toProfile(): array
    {
        return [
            'platform' => $this->platform,
            'sitemaps' => $this->sitemaps ?? [],
            'exclude' => $this->exclude ?? [],
            'sections' => $this->sections ?? [],
        ];
    }
}
