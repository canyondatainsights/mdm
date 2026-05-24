<?php

namespace App\Filament\Resources\WikiPageResource\Pages;

use App\Filament\Resources\WikiPageResource;
use App\Models\AuditLog;
use App\Services\Kb\WikiAuthor;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class EditWikiPage extends EditRecord
{
    protected static string $resource = WikiPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    /** Load the on-disk Markdown body (minus front-matter) into the editable content field. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $abs = rtrim(config('mdm.kb_path'), '/').'/'.$this->record->path;
        $data['content'] = is_file($abs)
            ? trim(YamlFrontMatter::parse(file_get_contents($abs))->body())
            : '';

        return $data;
    }

    /** Re-author the existing file (stable path) + re-ingest, rather than a metadata-only DB update. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $page = app(WikiAuthor::class)->write(
            section: $record->section ?? '01-foundations',
            title: $data['title'],
            body: $data['content'] ?? '',
            meta: Arr::only($data, ['mdm_vendor', 'data_platform', 'financial_model', 'domain', 'scope', 'product', 'product_version']),
            author: auth()->user()?->name,
            email: auth()->user()?->email,
            relPath: $record->path,
        );

        AuditLog::record('wiki.edited', ['path' => $page->path, 'title' => $page->title], 'WikiPage', (string) $page->id);

        return $page;
    }
}
