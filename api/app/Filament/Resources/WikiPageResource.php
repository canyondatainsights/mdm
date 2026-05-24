<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WikiPageResource\Pages;
use App\Models\WikiPage;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class WikiPageResource extends Resource
{
    protected static ?string $model = WikiPage::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-book-open';

    protected static string | \UnitEnum | null $navigationGroup = 'Knowledge Base';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Wiki Pages';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('path')
                ->disabled()
                ->maxLength(255),
            Forms\Components\TextInput::make('section')
                ->disabled(),
            Forms\Components\Select::make('mdm_vendor')
                ->options(fn () => collect(config('mdm.dimensions.mdm_vendor'))->mapWithKeys(fn ($v) => [$v => $v])->toArray())
                ->nullable(),
            Forms\Components\Select::make('data_platform')
                ->options(fn () => collect(config('mdm.dimensions.data_platform'))->mapWithKeys(fn ($v) => [$v => $v])->toArray())
                ->nullable(),
            Forms\Components\Select::make('financial_model')
                ->options(fn () => collect(config('mdm.dimensions.financial_model'))->mapWithKeys(fn ($v) => [$v => $v])->toArray())
                ->nullable(),
            Forms\Components\Select::make('domain')
                ->options(fn () => collect(config('mdm.dimensions.domain'))->mapWithKeys(fn ($v) => [$v => $v])->toArray())
                ->nullable(),
            Forms\Components\Select::make('scope')
                ->options(['neutral' => 'Neutral', 'vendor-specific' => 'Vendor-specific'])
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('path')
            ->columns([
                Tables\Columns\TextColumn::make('path')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('section')
                    ->sortable(),
                Tables\Columns\TextColumn::make('mdm_vendor')
                    ->label('Vendor')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('data_platform')
                    ->label('Platform')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('domain')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('scope')
                    ->badge()
                    ->color(fn (?string $state) => $state === 'neutral' ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('page_updated_at')
                    ->label('Last Updated')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('section')
                    ->options(fn () => WikiPage::distinct()->whereNotNull('section')->pluck('section', 'section')->toArray()),
                Tables\Filters\SelectFilter::make('mdm_vendor')
                    ->options(fn () => collect(config('mdm.dimensions.mdm_vendor'))->mapWithKeys(fn ($v) => [$v => $v])->toArray()),
                Tables\Filters\SelectFilter::make('data_platform')
                    ->options(fn () => collect(config('mdm.dimensions.data_platform'))->mapWithKeys(fn ($v) => [$v => $v])->toArray()),
                Tables\Filters\SelectFilter::make('scope')
                    ->options(['neutral' => 'Neutral', 'vendor-specific' => 'Vendor-specific']),
            ])
            ->recordActions([
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkAction::make('setVendor')
                    ->label('Set Vendor')
                    ->icon('heroicon-o-tag')
                    ->form([
                        Forms\Components\Select::make('mdm_vendor')
                            ->options(fn () => collect(config('mdm.dimensions.mdm_vendor'))->mapWithKeys(fn ($v) => [$v => $v])->toArray())
                            ->nullable(),
                    ])
                    ->action(fn ($records, array $data) => $records->each->update(['mdm_vendor' => $data['mdm_vendor']])),
                Actions\BulkAction::make('setPlatform')
                    ->label('Set Platform')
                    ->icon('heroicon-o-tag')
                    ->form([
                        Forms\Components\Select::make('data_platform')
                            ->options(fn () => collect(config('mdm.dimensions.data_platform'))->mapWithKeys(fn ($v) => [$v => $v])->toArray())
                            ->nullable(),
                    ])
                    ->action(fn ($records, array $data) => $records->each->update(['data_platform' => $data['data_platform']])),
                Actions\BulkAction::make('setDomain')
                    ->label('Set Domain')
                    ->icon('heroicon-o-tag')
                    ->form([
                        Forms\Components\Select::make('domain')
                            ->options(fn () => collect(config('mdm.dimensions.domain'))->mapWithKeys(fn ($v) => [$v => $v])->toArray())
                            ->nullable(),
                    ])
                    ->action(fn ($records, array $data) => $records->each->update(['domain' => $data['domain']])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWikiPages::route('/'),
            'edit' => Pages\EditWikiPage::route('/{record}/edit'),
        ];
    }
}
