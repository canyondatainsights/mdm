<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrawlerResource\Pages;
use App\Models\AuditLog;
use App\Models\Crawler;
use App\Services\Kb\CrawlerService;
use App\Services\Taxonomy\Taxonomy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Manage documentation-crawler profiles (sitemaps + section→category map) and run ad-hoc crawls.
 * Add a new site here and run it without a deploy; ingestion progress shows in Knowledge sources.
 */
class CrawlerResource extends Resource
{
    protected static ?string $model = Crawler::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string | \UnitEnum | null $navigationGroup = 'Knowledge Base';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Crawlers';

    public static function form(Schema $schema): Schema
    {
        $opts = fn (string $type) => collect(Taxonomy::values($type))->mapWithKeys(fn ($v) => [$v => $v])->all();

        return $schema->schema([
            Forms\Components\TextInput::make('key')->required()->maxLength(64)
                ->unique(ignoreRecord: true)
                ->helperText('Stable profile key, e.g. databricks, snowflake, oracle-docs.'),
            Forms\Components\TextInput::make('name')->required()->maxLength(128),
            Forms\Components\Select::make('platform')->options($opts('data_platform'))->searchable()
                ->helperText('Crawled pages are tagged data_platform=this (vendor-neutral re: MDM tool).'),
            Forms\Components\TagsInput::make('sitemaps')->required()
                ->placeholder('https://docs.example.com/sitemap.xml')
                ->helperText('One or more sitemap URLs.')->columnSpanFull(),
            Forms\Components\TagsInput::make('exclude')
                ->placeholder('/release-notes/')
                ->helperText('Skip URLs whose path contains any of these.')->columnSpanFull(),
            Forms\Components\Repeater::make('sections')
                ->helperText('Each rule selects + classifies pages. "Match" empty = exact path-segment match on Section; otherwise substring match on the URL path.')
                ->schema([
                    Forms\Components\TextInput::make('section')->required()->helperText('URL segment or label'),
                    Forms\Components\Select::make('domain')->options($opts('domain'))->default('general'),
                    Forms\Components\TextInput::make('product')->label('Product (optional)'),
                    Forms\Components\TagsInput::make('match')->label('Match substrings (optional)'),
                ])
                ->columns(2)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['section'] ?? null)
                ->columnSpanFull(),
            Forms\Components\Toggle::make('active')->default(true),
            Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('platform')->badge(),
                Tables\Columns\TextColumn::make('sections')->label('Sections')->state(fn (Crawler $r) => count($r->sections ?? [])),
                Tables\Columns\IconColumn::make('active')->boolean(),
            ])
            ->recordActions([
                Actions\Action::make('run')
                    ->label('Run crawl')
                    ->icon('heroicon-o-arrow-path')
                    ->modalDescription('Discover + queue pages from the sitemap(s). Unchanged pages are skipped; ingestion runs in the background.')
                    ->form([
                        Forms\Components\Toggle::make('dry_run')->label('Dry run (count only, no ingest)')->default(true),
                        Forms\Components\TextInput::make('sections')->label('Only sections (comma-separated, optional)'),
                        Forms\Components\TextInput::make('limit')->numeric()->default(0)->helperText('0 = all matched'),
                        Forms\Components\TextInput::make('sleep')->numeric()->default(0)->helperText('Seconds to stagger between jobs'),
                    ])
                    ->action(function (Crawler $record, array $data) {
                        $result = app(CrawlerService::class)->crawl($record->key, $record->toProfile(), [
                            'only' => array_filter(array_map('trim', explode(',', (string) ($data['sections'] ?? '')))),
                            'limit' => (int) ($data['limit'] ?? 0),
                            'sleep' => (int) ($data['sleep'] ?? 0),
                            'dryRun' => (bool) ($data['dry_run'] ?? true),
                        ]);
                        AuditLog::record('crawler.run', ['crawler' => $record->key, 'dry_run' => (bool) ($data['dry_run'] ?? true), 'matched' => $result['matched'], 'queued' => $result['queued']]);
                        Notification::make()
                            ->title(($data['dry_run'] ?? true)
                                ? "Matched {$result['matched']} page(s) (dry run)"
                                : "Queued {$result['queued']} of {$result['matched']} matched page(s)")
                            ->body(($data['dry_run'] ?? true) ? 'Nothing ingested.' : 'Run the queue worker; pages appear in Knowledge sources.')
                            ->success()
                            ->send();
                    }),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrawlers::route('/'),
            'create' => Pages\CreateCrawler::route('/create'),
            'edit' => Pages\EditCrawler::route('/{record}/edit'),
        ];
    }
}
