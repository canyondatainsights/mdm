<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WikiPageResource\Pages;
use App\Models\WikiPage;
use App\Services\Kb\WikiDrafter;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
        return true;
    }

    /** Existing wiki section directories (kb/wiki/*), for the section picker. */
    public static function sectionOptions(): array
    {
        $root = rtrim(config('mdm.kb_path'), '/').'/wiki';
        $dirs = is_dir($root) ? array_map('basename', glob($root.'/*', GLOB_ONLYDIR) ?: []) : [];
        if (empty($dirs)) {
            $dirs = ['01-foundations', '02-informatica-mdm', '03-data-quality', '04-pipelines-medallion',
                '05-snowflake', '06-databricks', '07-governance-consent', '08-patterns-playbooks', '09-decisions-adrs'];
        }
        sort($dirs);

        return array_combine($dirs, $dirs);
    }

    public static function form(Schema $schema): Schema
    {
        $opts = fn (string $type) => collect(\App\Services\Taxonomy\Taxonomy::values($type))->mapWithKeys(fn ($v) => [$v => $v])->toArray();

        return $schema->schema([
            Forms\Components\Placeholder::make('_explainer')
                ->hiddenLabel()
                ->content(new \Illuminate\Support\HtmlString(
                    '<div style="padding:.6rem .8rem;border-left:3px solid #2447d6;background:#eef2ff;border-radius:.4rem;font-size:.85rem;line-height:1.5;color:#1e2a52;">'
                    .'<strong>What is a wiki page?</strong> Curated, authored knowledge — the answers you want the assistant to give. '
                    .'Saving writes a Markdown file under <code>kb/wiki/&lt;section&gt;/</code>, chunks + embeds it, and makes it '
                    .'<em>immediately retrievable</em> in chat. Unlike uploaded sources (which await steward approval), wiki pages '
                    .'are first-class, version-controlled answers you maintain.</div>'
                ))
                ->visibleOn(['create', 'edit'])
                ->columnSpanFull(),
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Forms\Components\Select::make('section')
                ->options(fn () => static::sectionOptions())
                ->required()
                ->searchable()
                ->disabledOn('edit')
                ->helperText('The page is filed under kb/wiki/<section>/. Cannot be moved after creation.'),
            Forms\Components\TextInput::make('path')
                ->disabled()
                ->dehydrated(false)
                ->hiddenOn('create'),
            Forms\Components\MarkdownEditor::make('content')
                ->label('Content (Markdown)')
                ->required()
                ->fileAttachmentsDisk('kb_media')
                ->helperText('Markdown body. Use the image button to embed diagrams — they are stored with the page. A leading "# Title" and a revision-log table are added automatically if absent.')
                ->hintAction(
                    Actions\Action::make('draftAi')
                        ->label('Draft with AI')
                        ->icon('heroicon-m-sparkles')
                        ->modalHeading('Draft this page with AI')
                        ->modalDescription('Generates a Markdown draft from the title + tags below. Review and edit before saving — it appends to any existing content.')
                        ->modalSubmitActionLabel('Generate')
                        ->form([
                            Forms\Components\Textarea::make('instructions')
                                ->label('What should this page cover? (optional)')
                                ->rows(3)
                                ->placeholder('e.g. focus on match/merge tuning and survivorship rules'),
                        ])
                        ->action(function (array $data, Get $get, Set $set) {
                            $title = trim((string) $get('title'));
                            if ($title === '') {
                                Notification::make()->title('Add a title first')->warning()->send();

                                return;
                            }
                            try {
                                $body = app(WikiDrafter::class)->draft($title, [
                                    'mdm_vendor' => $get('mdm_vendor'),
                                    'data_platform' => $get('data_platform'),
                                    'product' => $get('product'),
                                    'domain' => $get('domain'),
                                    'financial_model' => $get('financial_model'),
                                ], $data['instructions'] ?? null);
                            } catch (\Throwable $e) {
                                Notification::make()->title('Generation failed')->body($e->getMessage())->danger()->send();

                                return;
                            }
                            $existing = trim((string) $get('content'));
                            $set('content', $existing ? $existing."\n\n".$body : $body);
                            Notification::make()->title('Draft inserted — review before saving')->success()->send();
                        }),
                )
                ->hintAction(
                    Actions\Action::make('importUrl')
                        ->label('Import from URL')
                        ->icon('heroicon-m-globe-alt')
                        ->modalHeading('Create from a web page')
                        ->modalDescription('Fetches the page’s readable content into the editor (appended to any existing content). Review and edit before saving.')
                        ->modalSubmitActionLabel('Fetch')
                        ->form([
                            Forms\Components\TextInput::make('url')
                                ->label('Content URL')
                                ->url()
                                ->required()
                                ->placeholder('https://…'),
                            Forms\Components\Toggle::make('structure')
                                ->label('Clean up & structure with AI')
                                ->default(true)
                                ->helperText('Rewrite the fetched page into a tidy wiki page (recommended). Off = raw extracted text.'),
                        ])
                        ->action(function (array $data, Get $get, Set $set) {
                            $url = trim((string) ($data['url'] ?? ''));
                            if ($url === '') {
                                return;
                            }
                            try {
                                $fetched = app(\App\Services\Kb\UrlFetcher::class)->fetch($url, withImages: true);
                            } catch (\Throwable $e) {
                                Notification::make()->title('Fetch failed')->body($e->getMessage())->danger()->send();

                                return;
                            }
                            // Seed the title from the page if the form's title is still empty.
                            $title = trim((string) $get('title'));
                            if ($title === '' && filled($fetched['title'])) {
                                $title = $fetched['title'];
                                $set('title', $title);
                            }
                            $body = $fetched['text'];
                            if (! empty($data['structure'])) {
                                try {
                                    $body = app(WikiDrafter::class)->draft($title ?: $fetched['title'], [
                                        'mdm_vendor' => $get('mdm_vendor'),
                                        'data_platform' => $get('data_platform'),
                                        'product' => $get('product'),
                                        'domain' => $get('domain'),
                                        'financial_model' => $get('financial_model'),
                                    ], null, $fetched['text']);
                                } catch (\Throwable $e) {
                                    Notification::make()->title('AI structuring failed — inserted raw text')->body($e->getMessage())->warning()->send();
                                }
                            }
                            // Download any content images (diagrams) into kb media and append as figures.
                            $figs = static::downloadImagesAsMarkdown($fetched['images'] ?? [], $url);
                            if ($figs !== '') {
                                $body = trim($body)."\n\n## Figures\n\n".$figs;
                            }

                            // Provenance footer (renders as a link in the reader).
                            $host = parse_url($url, PHP_URL_HOST) ?: 'source';
                            $body = trim($body)."\n\n*Source: [{$host}]({$url})*";

                            $existing = trim((string) $get('content'));
                            $set('content', $existing ? $existing."\n\n".$body : $body);
                            Notification::make()->title('Imported — review before saving')->success()->send();
                        }),
                )
                ->columnSpanFull(),
            Forms\Components\Select::make('mdm_vendor')->label('Vendor')->options($opts('mdm_vendor'))->nullable(),
            Forms\Components\TextInput::make('product')->nullable(),
            Forms\Components\Select::make('data_platform')->label('Platform')->options($opts('data_platform'))->nullable(),
            Forms\Components\TextInput::make('product_version')->label('Version')->nullable(),
            Forms\Components\Select::make('domain')->options($opts('domain'))->nullable(),
            Forms\Components\Select::make('financial_model')->label('Financial model')->options($opts('financial_model'))->nullable(),
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
                    ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('mdm_vendor'))->mapWithKeys(fn ($v) => [$v => $v])->toArray()),
                Tables\Filters\SelectFilter::make('data_platform')
                    ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('data_platform'))->mapWithKeys(fn ($v) => [$v => $v])->toArray()),
                Tables\Filters\SelectFilter::make('scope')
                    ->options(['neutral' => 'Neutral', 'vendor-specific' => 'Vendor-specific']),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkAction::make('setVendor')
                    ->label('Set Vendor')
                    ->icon('heroicon-o-tag')
                    ->form([
                        Forms\Components\Select::make('mdm_vendor')
                            ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('mdm_vendor'))->mapWithKeys(fn ($v) => [$v => $v])->toArray())
                            ->nullable(),
                    ])
                    ->action(fn ($records, array $data) => $records->each->update(['mdm_vendor' => $data['mdm_vendor']])),
                Actions\BulkAction::make('setPlatform')
                    ->label('Set Platform')
                    ->icon('heroicon-o-tag')
                    ->form([
                        Forms\Components\Select::make('data_platform')
                            ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('data_platform'))->mapWithKeys(fn ($v) => [$v => $v])->toArray())
                            ->nullable(),
                    ])
                    ->action(fn ($records, array $data) => $records->each->update(['data_platform' => $data['data_platform']])),
                Actions\BulkAction::make('setDomain')
                    ->label('Set Domain')
                    ->icon('heroicon-o-tag')
                    ->form([
                        Forms\Components\Select::make('domain')
                            ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('domain'))->mapWithKeys(fn ($v) => [$v => $v])->toArray())
                            ->nullable(),
                    ])
                    ->action(fn ($records, array $data) => $records->each->update(['domain' => $data['domain']])),
            ]);
    }

    /**
     * Download candidate content images into the kb_media disk and return markdown image references.
     * Filters non-images and tiny icons; caps the count. Used by the "Import from URL" action.
     *
     * @param  array<int, array{src:string, alt:string}>  $images
     */
    protected static function downloadImagesAsMarkdown(array $images, string $pageUrl): string
    {
        if (empty($images)) {
            return '';
        }
        $disk = \Illuminate\Support\Facades\Storage::disk('kb_media');
        $slug = \Illuminate\Support\Str::slug((parse_url($pageUrl, PHP_URL_HOST) ?: 'src').' '.trim((string) parse_url($pageUrl, PHP_URL_PATH), '/'))
            ?: substr(md5($pageUrl), 0, 10);
        $dir = 'imports/'.$slug;

        $figs = [];
        $n = 0;
        foreach ($images as $im) {
            if ($n >= 10) {
                break;
            }
            try {
                $resp = \Illuminate\Support\Facades\Http::timeout(15)
                    ->withHeaders(['User-Agent' => 'MDM-KnowledgeHub/1.0'])->get($im['src']);
                if (! $resp->successful()) {
                    continue;
                }
                $ct = strtolower((string) $resp->header('Content-Type'));
                if (! str_starts_with($ct, 'image/')) {
                    continue;
                }
                $bytes = $resp->body();
                if (strlen($bytes) < 3000) {
                    continue; // likely an icon/spacer
                }
                $ext = match (true) {
                    str_contains($ct, 'png') => 'png',
                    str_contains($ct, 'jpeg'), str_contains($ct, 'jpg') => 'jpg',
                    str_contains($ct, 'gif') => 'gif',
                    str_contains($ct, 'webp') => 'webp',
                    str_contains($ct, 'svg') => 'svg',
                    default => pathinfo(parse_url($im['src'], PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'img',
                };
                $name = $dir.'/'.(++$n).'.'.$ext;
                $disk->put($name, $bytes);
                $alt = str_replace(['[', ']'], '', $im['alt'] !== '' ? $im['alt'] : 'Figure '.$n);
                $figs[] = "![{$alt}](/media/wiki/{$name})";
            } catch (\Throwable) {
                continue;
            }
        }

        return implode("\n\n", $figs);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWikiPages::route('/'),
            'create' => Pages\CreateWikiPage::route('/create'),
            'view' => Pages\ViewWikiPage::route('/{record}'),
            'edit' => Pages\EditWikiPage::route('/{record}/edit'),
        ];
    }
}
