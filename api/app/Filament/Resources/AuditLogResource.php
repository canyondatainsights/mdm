<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static string | \UnitEnum | null $navigationGroup = 'Governance';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Audit Log';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Entry Details')->schema([
                Infolists\Components\TextEntry::make('action')->badge(),
                Infolists\Components\TextEntry::make('user.name')->label('User'),
                Infolists\Components\TextEntry::make('subject_type'),
                Infolists\Components\TextEntry::make('subject_id'),
                Infolists\Components\TextEntry::make('git_commit')
                    ->label('Git Commit')
                    ->placeholder('—')
                    ->copyable(),
                Infolists\Components\TextEntry::make('created_at')->dateTime(),
            ])->columns(2),
            Section::make('Metadata')->schema([
                Infolists\Components\KeyValueEntry::make('meta'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('summary')
                    ->label('Event')
                    ->state(fn (AuditLog $record) => $record->summary())
                    ->wrap()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state) => AuditLog::actionColor($state))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->placeholder('system'),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state, AuditLog $record) => $state ? class_basename($state).($record->subject_id ? " #{$record->subject_id}" : '') : null)
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options(fn () => AuditLog::distinct()->pluck('action', 'action')->toArray()),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}
