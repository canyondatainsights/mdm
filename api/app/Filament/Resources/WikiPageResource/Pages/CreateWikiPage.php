<?php

namespace App\Filament\Resources\WikiPageResource\Pages;

use App\Filament\Resources\WikiPageResource;
use App\Models\AuditLog;
use App\Services\Kb\WikiAuthor;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateWikiPage extends CreateRecord
{
    protected static string $resource = WikiPageResource::class;

    /** Author the page on disk + ingest it (overrides Eloquent create); the Ingestor upserts the row. */
    protected function handleRecordCreation(array $data): Model
    {
        $page = app(WikiAuthor::class)->write(
            section: $data['section'] ?? '01-foundations',
            title: $data['title'],
            body: $data['content'] ?? '',
            meta: Arr::only($data, ['mdm_vendor', 'data_platform', 'financial_model', 'domain', 'scope', 'product', 'product_version']),
            author: auth()->user()?->name,
            email: auth()->user()?->email,
        );

        AuditLog::record('wiki.authored', ['path' => $page->path, 'title' => $page->title], 'WikiPage', (string) $page->id);

        return $page;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
