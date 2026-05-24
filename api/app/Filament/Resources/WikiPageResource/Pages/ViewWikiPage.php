<?php

namespace App\Filament\Resources\WikiPageResource\Pages;

use App\Filament\Resources\WikiPageResource;
use App\Models\Chunk;
use App\Models\WikiPage;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ViewWikiPage extends ViewRecord
{
    protected static string $resource = WikiPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Page')
                ->description('A wiki page is curated, authored knowledge — retrievable in chat alongside uploaded sources, but maintained by stewards and version-controlled.')
                ->schema([
                    Infolists\Components\TextEntry::make('title')->weight('bold')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('path')->copyable()->color('gray'),
                    Infolists\Components\TextEntry::make('section')->badge(),
                    Infolists\Components\TextEntry::make('mdm_vendor')->label('Vendor')->badge()->color('info')->placeholder('—'),
                    Infolists\Components\TextEntry::make('data_platform')->label('Platform')->badge()->color('info')->placeholder('—'),
                    Infolists\Components\TextEntry::make('product')->placeholder('—'),
                    Infolists\Components\TextEntry::make('domain')->badge()->placeholder('—'),
                    Infolists\Components\TextEntry::make('financial_model')->label('Financial model')->placeholder('—'),
                    Infolists\Components\TextEntry::make('scope')->badge()
                        ->color(fn (?string $state) => $state === 'neutral' ? 'success' : 'gray'),
                    Infolists\Components\TextEntry::make('chunk_count')->label('Chunks')
                        ->state(fn (WikiPage $record) => Chunk::where('wiki_page_id', $record->id)->count()),
                    Infolists\Components\TextEntry::make('page_updated_at')->label('Last updated')->dateTime()->placeholder('—'),
                ])->columns(2),
            Section::make('Content')->schema([
                Infolists\Components\TextEntry::make('_body')
                    ->hiddenLabel()
                    ->html()
                    ->state(fn (WikiPage $record) => new HtmlString(Str::markdown(static::bodyFor($record))))
                    ->columnSpanFull(),
            ])->collapsible(),
        ]);
    }

    /** Reconstruct the page body from its chunks (the indexed text), falling back to the file on disk. */
    protected static function bodyFor(WikiPage $page): string
    {
        $body = Chunk::where('wiki_page_id', $page->id)->orderBy('chunk_index')->pluck('content')->implode("\n\n");
        if ($body !== '') {
            return $body;
        }
        $abs = rtrim(config('mdm.kb_path'), '/').'/'.$page->path;

        return is_file($abs) ? (string) file_get_contents($abs) : '_No content indexed yet._';
    }
}
