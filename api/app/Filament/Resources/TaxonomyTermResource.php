<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxonomyTermResource\Pages;
use App\Models\TaxonomyTerm;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Admin CRUD for the lockable taxonomy. Add a vendor, platform, subject/domain or per-vendor
 * product here and it becomes available in the Stack-Lock + upload forms immediately — no
 * code change or deploy. (Backs Taxonomy::dimensions()/products().)
 */
class TaxonomyTermResource extends Resource
{
    protected static ?string $model = TaxonomyTerm::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    protected static string | \UnitEnum | null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Taxonomy';

    protected static ?string $modelLabel = 'taxonomy term';

    private const TYPE_OPTIONS = [
        'mdm_vendor' => 'MDM vendor',
        'data_platform' => 'Data platform',
        'financial_model' => 'Financial model',
        'domain' => 'Data domain / subject',
        'product' => 'Product (per vendor)',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('type')
                ->options(self::TYPE_OPTIONS)
                ->required()
                ->live()
                ->helperText('Products are scoped to a vendor; everything else is a lockable dimension value.'),
            Forms\Components\TextInput::make('value')
                ->required()
                ->maxLength(255)
                ->helperText('Canonical value stored on documents. Data domains (customer, product, supplier…) and capability subjects (data-governance, data-quality…) are both the "Data domain / subject" type.'),
            Forms\Components\TextInput::make('label')
                ->maxLength(255)
                ->placeholder('Optional display label (defaults to the value).'),
            Forms\Components\Select::make('vendor')
                ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('mdm_vendor'))->mapWithKeys(fn ($v) => [$v => $v])->all())
                ->searchable()
                ->visible(fn (Get $get) => $get('type') === 'product')
                ->required(fn (Get $get) => $get('type') === 'product')
                ->helperText('Which vendor this product belongs to.'),
            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('active')
                ->default(true)
                ->helperText('Inactive terms are hidden from the lock/upload forms but keep existing tags intact.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->badge()->sortable()->searchable(),
                Tables\Columns\TextColumn::make('value')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('label')->toggleable(),
                Tables\Columns\TextColumn::make('vendor')->badge()->sortable()->searchable(),
                Tables\Columns\TextColumn::make('sort_order')->label('Sort')->sortable(),
                Tables\Columns\IconColumn::make('active')->boolean()->sortable(),
            ])
            ->defaultSort('type')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(self::TYPE_OPTIONS),
                Tables\Filters\TernaryFilter::make('active'),
            ])
            ->recordActions([
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxonomyTerms::route('/'),
            'create' => Pages\CreateTaxonomyTerm::route('/create'),
            'edit' => Pages\EditTaxonomyTerm::route('/{record}/edit'),
        ];
    }
}
