<?php

namespace App\Filament\Widgets;

use App\Services\Kb\KbStats;
use Filament\Widgets\ChartWidget;

/** Chunk distribution across MDM vendors — how deep coverage runs per vendor. */
class VendorDepthChart extends ChartWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Vendor depth';

    protected ?string $description = 'Chunks per MDM vendor';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $by = app(KbStats::class)->byVendor();

        return [
            'datasets' => [[
                'label' => 'Chunks',
                'data' => array_values($by),
                'backgroundColor' => '#2447d6',
                'borderRadius' => 4,
            ]],
            'labels' => array_keys($by),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
