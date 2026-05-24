<?php

namespace App\Filament\Resources\TaxonomyTermResource\Pages;

use App\Filament\Resources\TaxonomyTermResource;
use App\Services\Taxonomy\Taxonomy;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTaxonomyTerm extends EditRecord
{
    protected static string $resource = TaxonomyTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        Taxonomy::flush();
    }
}
