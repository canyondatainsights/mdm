<?php

namespace App\Filament\Resources\SourceResource\Pages;

use App\Filament\Resources\SourceResource;
use App\Jobs\IngestUploadedFile;
use App\Jobs\IngestUrlSource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListSources extends ListRecords
{
    protected static string $resource = SourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('upload')
                ->label('Upload documentation')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('Expand the knowledge base')
                ->modalDescription('Upload one or more PDF/MD/TXT docs, tagged by vendor + product + version. Ingestion runs in the background.')
                ->modalSubmitActionLabel('Upload & ingest')
                ->form([
                    Forms\Components\FileUpload::make('files')
                        ->label('Documents')
                        ->multiple()
                        ->required(fn (Get $get) => blank($get('url')))
                        ->preserveFilenames()
                        ->disk('local')
                        ->directory('kb-uploads-tmp')
                        ->acceptedFileTypes(['application/pdf', 'text/plain', 'text/markdown'])
                        ->maxSize(51200)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('url')
                        ->label('…or a reference URL')
                        ->url()
                        ->live(onBlur: true)
                        ->placeholder('https://docs.informatica.com/…')
                        ->helperText('The page is fetched, its readable text extracted, and ingested like a document.')
                        ->columnSpanFull(),
                    Forms\Components\Select::make('mdm_vendor')
                        ->label('Vendor')
                        ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('mdm_vendor'))->mapWithKeys(fn ($v) => [$v => $v])->all())
                        ->live(),
                    Forms\Components\Select::make('data_platform')
                        ->label('Data platform')
                        ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('data_platform'))->mapWithKeys(fn ($v) => [$v => $v])->all()),
                    Forms\Components\TextInput::make('product')
                        ->label('Product')
                        ->maxLength(128)
                        ->datalist(fn (Get $get) => \App\Services\Taxonomy\Taxonomy::productsFor($get('mdm_vendor')))
                        ->helperText('Free text. Suggestions appear for the selected vendor (e.g. Customer 360).'),
                    Forms\Components\TextInput::make('product_version')
                        ->label('Version')
                        ->maxLength(64)
                        ->placeholder('e.g. 10.5 or SaaS 2024.x'),
                    Forms\Components\Select::make('domain')
                        ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('domain'))->mapWithKeys(fn ($v) => [$v => $v])->all()),
                    Forms\Components\Select::make('scope')
                        ->options(['vendor-specific' => 'Vendor-specific', 'neutral' => 'Neutral'])
                        ->default('vendor-specific'),
                ])
                ->action(fn (array $data) => $this->handleUpload($data)),
        ];
    }

    protected function handleUpload(array $data): void
    {
        $root = rtrim(config('mdm.kb_path'), '/');
        $vendor = $data['mdm_vendor'] ?? null;
        $platform = $data['data_platform'] ?? null;
        $product = $data['product'] ?? null;
        $version = $data['product_version'] ?? null;
        $scope = $data['scope'] ?? (($vendor || $platform) ? 'vendor-specific' : null);

        $category = preg_replace('/[^a-z0-9\-]/', '', strtolower($vendor ?? 'uploads')) ?: 'uploads';
        $segments = [$root, 'raw', $category];
        if ($product) {
            $segments[] = Str::slug($product);
        }
        if ($version) {
            $segments[] = Str::slug($version);
        }
        $dir = implode('/', $segments);
        @mkdir($dir, 0775, true);

        $overrides = array_filter([
            'mdm_vendor' => $vendor,
            'data_platform' => $platform,
            'domain' => $data['domain'] ?? null,
            'scope' => $scope,
            'product' => $product,
            'product_version' => $version,
        ], fn ($v) => $v !== null && $v !== '');

        $count = 0;
        foreach ((array) ($data['files'] ?? []) as $stored) {
            $srcAbs = Storage::disk('local')->path($stored);
            if (! is_file($srcAbs)) {
                continue;
            }
            $ext = strtolower(pathinfo($srcAbs, PATHINFO_EXTENSION));
            $safe = Str::slug(pathinfo($srcAbs, PATHINFO_FILENAME)).'.'.$ext;
            $destAbs = $dir.'/'.$safe;
            rename($srcAbs, $destAbs);

            IngestUploadedFile::dispatch($destAbs, $root, $overrides, auth()->id());
            $count++;
        }

        if (! empty($data['url'])) {
            IngestUrlSource::dispatch($data['url'], $dir, $root, $overrides, auth()->id());
            $count++;
        }

        Notification::make()
            ->title("Queued {$count} source(s) for ingestion")
            ->body('Chunks will appear here once the queue worker processes them.')
            ->success()
            ->send();
    }
}
