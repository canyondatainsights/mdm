<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SourceResource\Pages;
use App\Models\Source;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SourceResource extends Resource
{
    protected static ?string $model = Source::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static string | \UnitEnum | null $navigationGroup = 'Knowledge Base';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Source Details')->schema([
                Infolists\Components\TextEntry::make('title'),
                Infolists\Components\TextEntry::make('path')->copyable(),
                Infolists\Components\TextEntry::make('doc_type')->badge(),
                Infolists\Components\TextEntry::make('pages'),
                Infolists\Components\IconEntry::make('approved')->boolean(),
                Infolists\Components\TextEntry::make('uploader.name')->label('Uploaded by')->placeholder('system'),
                Infolists\Components\TextEntry::make('created_at')->dateTime(),
            ])->columns(2),
            Section::make('Isolation Tags')->schema([
                Infolists\Components\TextEntry::make('mdm_vendor')->placeholder('—'),
                Infolists\Components\TextEntry::make('data_platform')->placeholder('—'),
                Infolists\Components\TextEntry::make('financial_model')->placeholder('—'),
                Infolists\Components\TextEntry::make('domain')->placeholder('—'),
                Infolists\Components\TextEntry::make('scope')->placeholder('—'),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('doc_type')->badge(),
                Tables\Columns\TextColumn::make('mdm_vendor')
                    ->label('Vendor')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('data_platform')
                    ->label('Platform')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('domain')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('approved')->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('approved'),
                Tables\Filters\SelectFilter::make('doc_type')
                    ->options(fn () => Source::distinct()->whereNotNull('doc_type')->pluck('doc_type', 'doc_type')->toArray()),
                Tables\Filters\SelectFilter::make('mdm_vendor')
                    ->options(fn () => collect(config('mdm.dimensions.mdm_vendor'))->mapWithKeys(fn ($v) => [$v => $v])->toArray()),
                Tables\Filters\SelectFilter::make('data_platform')
                    ->options(fn () => collect(config('mdm.dimensions.data_platform'))->mapWithKeys(fn ($v) => [$v => $v])->toArray()),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\Action::make('toggleApproval')
                    ->icon(fn (Source $record) => $record->approved ? 'heroicon-o-x-mark' : 'heroicon-o-check')
                    ->color(fn (Source $record) => $record->approved ? 'danger' : 'success')
                    ->label(fn (Source $record) => $record->approved ? 'Revoke' : 'Approve')
                    ->requiresConfirmation()
                    ->action(fn (Source $record) => $record->update(['approved' => ! $record->approved])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSources::route('/'),
            'view' => Pages\ViewSource::route('/{record}'),
        ];
    }
}
