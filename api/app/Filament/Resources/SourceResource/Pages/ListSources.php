<?php

namespace App\Filament\Resources\SourceResource\Pages;

use App\Filament\Resources\SourceResource;
use App\Jobs\IngestUploadedFile;
use App\Jobs\IngestUrlSource;
use App\Models\Source;
use App\Services\Kb\Classifier;
use App\Services\Kb\DocumentParser;
use App\Services\Kb\UploadTagger;
use App\Services\Kb\UrlFetcher;
use App\Services\Taxonomy\Taxonomy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListSources extends ListRecords
{
    protected static string $resource = SourceResource::class;

    protected function getHeaderActions(): array
    {
        $opts = fn (string $type) => collect(Taxonomy::values($type))->mapWithKeys(fn ($v) => [$v => $v])->all();

        return [
            Actions\Action::make('upload')
                ->label('Upload documentation')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('Expand the knowledge base')
                ->modalDescription('Add PDF/MD/TXT docs, example scripts (.sql, .py, .json, …), and/or a reference URL. They are scanned and auto-tagged by vendor + product + subject — review each below before ingesting.')
                ->modalSubmitActionLabel('Confirm & ingest')
                ->form([
                    Forms\Components\FileUpload::make('files')
                        ->label('Documents')
                        ->multiple()
                        ->preserveFilenames()
                        ->disk('local')
                        ->directory('kb-uploads-tmp')
                        ->acceptedFileTypes([
                            'application/pdf', 'text/plain', 'text/markdown', 'text/csv',
                            'application/json', 'application/xml', 'text/xml', 'application/x-yaml',
                            'application/octet-stream',
                        ])
                        ->maxSize(51200)
                        ->live()
                        ->afterStateUpdated(fn ($state, Get $get, Set $set) => $this->onFilesChanged($state, $get, $set))
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('url')
                        ->label('…or a reference URL')
                        ->url()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Get $get, Set $set) => $this->onUrlChanged($state, $get, $set))
                        ->placeholder('https://docs.informatica.com/…')
                        ->helperText('The page is fetched, classified, and ingested like a document.')
                        ->columnSpanFull(),
                    Forms\Components\Repeater::make('items')
                        ->label('Suggested tags')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columns(2)
                        ->visible(fn (Get $get) => filled($get('items')))
                        ->itemLabel(fn (array $state) => ($state['filename'] ?? 'source').' · '.ucfirst($state['confidence'] ?? 'low'))
                        ->schema([
                            Forms\Components\Hidden::make('filename'),
                            Forms\Components\Hidden::make('is_url'),
                            Forms\Components\Hidden::make('stored'),
                            Forms\Components\Hidden::make('confidence'),
                            Forms\Components\Hidden::make('new_subject_value'),
                            Forms\Components\Hidden::make('new_subject_label'),
                            Forms\Components\Select::make('mdm_vendor')->label('Vendor')->options($opts('mdm_vendor'))->live(),
                            Forms\Components\TextInput::make('product')->label('Product')
                                ->datalist(fn (Get $get) => Taxonomy::productsFor($get('mdm_vendor'))),
                            Forms\Components\Select::make('data_platform')->label('Data platform')->options($opts('data_platform')),
                            Forms\Components\TextInput::make('product_version')->label('Version'),
                            Forms\Components\Select::make('domain')->label('Subject / domain')
                                ->options($opts('domain'))
                                ->disabled(fn (Get $get) => (bool) $get('apply_new')),
                            Forms\Components\Select::make('scope')
                                ->options(['vendor-specific' => 'Vendor-specific', 'neutral' => 'Neutral']),
                            Forms\Components\Toggle::make('apply_new')
                                ->label(fn (Get $get) => 'Add new subject: '.$get('new_subject_label'))
                                ->visible(fn (Get $get) => filled($get('new_subject_value')))
                                ->columnSpanFull(),
                            Forms\Components\Hidden::make('reasoning'),
                            Forms\Components\Placeholder::make('reasoning_note')
                                ->hiddenLabel()
                                ->content(fn (Get $get) => $get('reasoning'))
                                ->visible(fn (Get $get) => filled($get('reasoning')))
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(fn (array $data) => $this->ingest($data)),
        ];
    }

    /** Classify newly-added files into review rows, preserving any existing URL row. */
    protected function onFilesChanged($state, Get $get, Set $set): void
    {
        $rows = [];
        foreach ((array) $state as $stored) {
            $path = is_string($stored) ? Storage::disk('local')->path($stored)
                : (is_object($stored) && method_exists($stored, 'getRealPath') ? $stored->getRealPath() : null);
            if (! $path || ! is_file($path)) {
                continue;
            }
            $name = is_string($stored) ? basename($stored) : $stored->getClientOriginalName();
            $rows[] = $this->suggestRow($name, false, $path, is_string($stored) ? $stored : null);
        }
        $urlRows = collect($get('items') ?? [])->filter(fn ($r) => $r['is_url'] ?? false)->values()->all();
        $set('items', array_merge($rows, $urlRows));
    }

    /** Classify the reference URL into a review row, preserving file rows. */
    protected function onUrlChanged($state, Get $get, Set $set): void
    {
        $fileRows = collect($get('items') ?? [])->reject(fn ($r) => $r['is_url'] ?? false)->values()->all();
        $urlRows = filled($state) ? [$this->suggestRow($state, true, null, null, $state)] : [];
        $set('items', array_merge($fileRows, $urlRows));
    }

    /** Build a review row from a classifier suggestion for a file or URL. */
    protected function suggestRow(string $name, bool $isUrl, ?string $absPath = null, ?string $stored = null, ?string $url = null): array
    {
        try {
            $excerpt = $isUrl ? app(UrlFetcher::class)->excerpt($url) : app(DocumentParser::class)->excerpt($absPath);
            $s = $excerpt === ''
                ? ['confidence' => 'low', 'reasoning' => 'Could not extract readable text.']
                : app(Classifier::class)->classify($name, $excerpt);
        } catch (\Throwable $e) {
            $s = ['confidence' => 'low', 'reasoning' => 'Classify failed: '.$e->getMessage()];
        }

        return [
            'filename' => $name,
            'is_url' => $isUrl,
            'stored' => $stored,
            'mdm_vendor' => $s['mdm_vendor'] ?? null,
            'data_platform' => $s['data_platform'] ?? null,
            'product' => $s['product'] ?? null,
            'product_version' => null,
            'domain' => $s['domain'] ?? null,
            'scope' => ($s['mdm_vendor'] ?? null) || ($s['data_platform'] ?? null) ? 'vendor-specific' : 'neutral',
            'new_subject_value' => $s['proposed_subject']['value'] ?? null,
            'new_subject_label' => $s['proposed_subject']['label'] ?? null,
            'apply_new' => false,
            'confidence' => $s['confidence'] ?? 'low',
            'reasoning' => $s['reasoning'] ?? null,
        ];
    }

    /** Ingest each reviewed row with its confirmed tags, persisting any approved new subjects. */
    protected function ingest(array $data): void
    {
        $tagger = app(UploadTagger::class);
        $items = $data['items'] ?? [];

        $newSubjectSets = [];
        foreach ($items as $it) {
            if (! empty($it['apply_new']) && ! empty($it['new_subject_value'])) {
                $newSubjectSets[] = ['new_subject' => ['value' => $it['new_subject_value'], 'label' => $it['new_subject_label'] ?? null]];
            }
        }
        $tagger->persistNewSubjects(null, $newSubjectSets);

        $root = rtrim(config('mdm.kb_path'), '/');
        $count = 0;
        foreach ($items as $it) {
            $useNew = ! empty($it['apply_new']) && ! empty($it['new_subject_value']);
            $overrides = $tagger->buildOverrides($tagger->coerceTags([
                'mdm_vendor' => $it['mdm_vendor'] ?? null,
                'data_platform' => $it['data_platform'] ?? null,
                'product' => $it['product'] ?? null,
                'product_version' => $it['product_version'] ?? null,
                'domain' => $useNew ? $it['new_subject_value'] : ($it['domain'] ?? null),
                'scope' => $it['scope'] ?? null,
            ]));
            $dir = $tagger->destDir($root, $overrides);
            @mkdir($dir, 0775, true);

            if (! empty($it['is_url'])) {
                $url = (string) $it['filename'];
                $host = parse_url($url, PHP_URL_HOST) ?: 'link';
                $leaf = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_FILENAME);
                $slug = Str::slug($host.'-'.$leaf) ?: 'reference-'.substr(md5($url), 0, 8);
                $urlRel = ltrim(str_replace($root, '', $dir.'/'.$slug.'.md'), '/');
                Source::markQueued($urlRel, $overrides)->update(['doc_type' => 'URL', 'owner' => $url]);
                IngestUrlSource::dispatch($url, $urlRel, $root, $overrides, auth()->id());
                $count++;

                continue;
            }

            $srcAbs = ! empty($it['stored']) ? Storage::disk('local')->path($it['stored']) : null;
            if (! $srcAbs || ! is_file($srcAbs)) {
                continue;
            }
            $safe = Str::slug(pathinfo($it['filename'], PATHINFO_FILENAME)).'.'.strtolower(pathinfo($it['filename'], PATHINFO_EXTENSION));
            $destAbs = $dir.'/'.$safe;
            rename($srcAbs, $destAbs);
            $rel = ltrim(str_replace($root, '', $destAbs), '/');
            Source::markQueued($rel, $overrides);
            IngestUploadedFile::dispatch($destAbs, $root, $overrides, auth()->id());
            $count++;
        }

        Notification::make()
            ->title("Queued {$count} source(s) for ingestion")
            ->body('Chunks appear once the queue worker processes them; new sources await approval.')
            ->success()
            ->send();
    }
}
