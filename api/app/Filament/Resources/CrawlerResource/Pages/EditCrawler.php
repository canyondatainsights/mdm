<?php

namespace App\Filament\Resources\CrawlerResource\Pages;

use App\Filament\Resources\CrawlerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCrawler extends EditRecord
{
    protected static string $resource = CrawlerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
