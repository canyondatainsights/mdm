<?php

namespace App\Filament\Widgets;

use App\Services\Kb\KbStats;
use Filament\Widgets\ChartWidget;

/** Chunk distribution across subjects/domains — subject-matter depth of the KB. */
class SubjectDepthChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Subject-matter depth';

    protected ?string $description = 'Chunks per subject / domain';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $by = app(KbStats::class)->byDomain();

        return [
            'datasets' => [[
                'label' => 'Chunks',
                'data' => array_values($by),
                'backgroundColor' => '#0e7490',
                'borderRadius' => 4,
            ]],
            'labels' => array_keys($by),
        ];
    }

    protected function getOptions(): array
    {
        // Horizontal bars — domain labels read better on the y-axis.
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['beginAtZero' => true]],
        ];
    }
}
