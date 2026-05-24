<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StewardshipTaskResource;
use App\Services\Kb\KbStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Headline KB coverage — sources, chunks, wiki pages, open steward work, taxonomy breadth. */
class KnowledgeBaseStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $heading = 'Knowledge base';

    protected function getStats(): array
    {
        $kb = app(KbStats::class);
        $t = $kb->totals();
        $tax = $kb->taxonomyDepth();
        $pending = $kb->pendingStewardship();

        return [
            Stat::make('Chunks', number_format($t['chunks']))
                ->description('Embedded, retrievable passages')
                ->descriptionIcon('heroicon-m-cube-transparent')
                ->color('primary'),
            Stat::make('Sources', number_format($t['sources']))
                ->description($t['approved'].' approved · '.$t['pending_sources'].' pending')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('gray'),
            Stat::make('Wiki pages', number_format($t['wiki_pages']))
                ->description('Curated, authored answers')
                ->descriptionIcon('heroicon-m-book-open')
                ->color('gray'),
            Stat::make('Open steward requests', $pending)
                ->description($pending ? 'Awaiting review' : 'All clear')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color($pending ? 'warning' : 'success')
                ->url(StewardshipTaskResource::getUrl('index')),
            Stat::make('Vendors', $tax['vendors'])
                ->description($tax['platforms'].' platforms · '.$tax['domains'].' subjects · '.$tax['products'].' products')
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('gray'),
        ];
    }
}
