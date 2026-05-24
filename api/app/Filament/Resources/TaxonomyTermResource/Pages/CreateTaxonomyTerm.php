<?php

namespace App\Filament\Resources\TaxonomyTermResource\Pages;

use App\Filament\Resources\TaxonomyTermResource;
use App\Services\Taxonomy\Taxonomy;
use Filament\Resources\Pages\CreateRecord;

class CreateTaxonomyTerm extends CreateRecord
{
    protected static string $resource = TaxonomyTermResource::class;

    protected function afterCreate(): void
    {
        Taxonomy::flush();
    }
}
