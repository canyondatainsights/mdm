<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StewardshipTaskResource\Pages;
use App\Jobs\ApplyStewardshipTask;
use App\Models\AuditLog;
use App\Models\StewardshipTask;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StewardshipTaskResource extends Resource
{
    protected static ?string $model = StewardshipTask::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string | \UnitEnum | null $navigationGroup = 'Governance';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Stewardship Queue';

    public static function getNavigationBadge(): ?string
    {
        return (string) StewardshipTask::where('status', 'pending')->count() ?: null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('summary')->disabled(),
            Forms\Components\Select::make('type')
                ->options(['wiki_edit' => 'Wiki Edit', 'adr' => 'ADR'])
                ->disabled(),
            Forms\Components\TextInput::make('target_path')->disabled(),
            Forms\Components\Textarea::make('proposed_content')
                ->disabled()
                ->rows(12)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('diff')
                ->disabled()
                ->rows(8)
                ->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Task Details')->schema([
                Infolists\Components\TextEntry::make('summary'),
                Infolists\Components\TextEntry::make('type')->badge(),
                Infolists\Components\TextEntry::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Infolists\Components\TextEntry::make('target_path')
                    ->label('Target Path')
                    ->placeholder('To be determined on apply'),
                Infolists\Components\TextEntry::make('proposer.name')->label('Proposed by'),
                Infolists\Components\TextEntry::make('reviewer.name')->label('Reviewed by'),
                Infolists\Components\TextEntry::make('reviewed_at')->dateTime(),
                Infolists\Components\TextEntry::make('created_at')->dateTime(),
            ])->columns(2),
            Section::make('Proposed Content')->schema([
                Infolists\Components\TextEntry::make('proposed_content')
                    ->prose()
                    ->columnSpanFull(),
            ]),
            Section::make('Diff')->schema([
                Infolists\Components\TextEntry::make('diff')
                    ->prose()
                    ->columnSpanFull(),
            ])->visible(fn (StewardshipTask $record) => filled($record->diff)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()
                    ->color(fn (string $state) => $state === 'adr' ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('summary')
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\TextColumn::make('target_path')
                    ->limit(40)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('proposer.name')
                    ->label('Proposer'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'wiki_edit' => 'Wiki Edit',
                        'adr' => 'ADR',
                    ]),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve stewardship task')
                    ->modalDescription('This will apply the proposed content to the KB, commit to git, and re-index affected chunks.')
                    ->visible(fn (StewardshipTask $record) => $record->status === 'pending')
                    ->action(function (StewardshipTask $record) {
                        $record->update([
                            'status' => 'approved',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        AuditLog::record('stewardship.approved', ['task_id' => $record->id], 'StewardshipTask', (string) $record->id);
                        ApplyStewardshipTask::dispatch($record);
                    }),
                Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (StewardshipTask $record) => $record->status === 'pending')
                    ->action(function (StewardshipTask $record) {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        AuditLog::record('stewardship.rejected', ['task_id' => $record->id], 'StewardshipTask', (string) $record->id);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStewardshipTasks::route('/'),
            'view' => Pages\ViewStewardshipTask::route('/{record}'),
        ];
    }
}
