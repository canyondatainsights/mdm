<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StewardshipTaskResource;
use App\Models\StewardshipTask;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** The open steward queue at a glance — pending requests, newest first, click through to review. */
class OpenStewardRequestsWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Open steward requests';

    public function table(Table $table): Table
    {
        return $table
            ->query(StewardshipTask::query()->where('status', 'pending')->latest())
            ->emptyStateHeading('No open requests')
            ->emptyStateDescription('The stewardship queue is clear.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('type')->badge()->color('warning'),
                TextColumn::make('summary')->limit(70)->wrap()->label('Request'),
                TextColumn::make('proposer.name')->label('Requested by')->placeholder('—'),
                TextColumn::make('created_at')->since()->label('When')->sortable(),
            ])
            ->recordUrl(fn (StewardshipTask $record) => StewardshipTaskResource::getUrl('view', ['record' => $record]));
    }
}
