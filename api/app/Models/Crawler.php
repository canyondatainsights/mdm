<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A documentation crawler profile (sitemaps + exclude patterns + a section→{product,domain,match}
 * map). Managed in the Filament admin and run ad-hoc; `CrawlerService` consumes `toProfile()`.
 */
class Crawler extends Model
{
    protected $fillable = ['key', 'name', 'platform', 'sitemaps', 'exclude', 'sections', 'active', 'schedule', 'last_run_at', 'sort_order', 'notes'];

    protected $casts = [
        'sitemaps' => 'array',
        'exclude' => 'array',
        'sections' => 'array',
        'active' => 'boolean',
        'last_run_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    /** Is an automated run due now, given the schedule + last_run_at? */
    public function isDue(): bool
    {
        if (! $this->active || in_array($this->schedule, [null, '', 'off'], true)) {
            return false;
        }
        if (! $this->last_run_at) {
            return true;
        }

        return $this->last_run_at->lte(match ($this->schedule) {
            'daily' => now()->subDay(),
            'weekly' => now()->subWeek(),
            'monthly' => now()->subMonth(),
            default => now()->addCentury(), // unknown cadence => never due
        });
    }

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
