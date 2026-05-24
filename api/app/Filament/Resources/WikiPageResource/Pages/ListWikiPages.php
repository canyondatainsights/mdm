<?php

namespace App\Filament\Resources\WikiPageResource\Pages;

use App\Filament\Resources\WikiPageResource;
use Filament\Resources\Pages\ListRecords;

class ListWikiPages extends ListRecords
{
    protected static string $resource = WikiPageResource::class;
}
