<?php

namespace App\Filament\Resources\TaxonomyTermResource\Pages;

use App\Filament\Resources\TaxonomyTermResource;
use App\Models\AuditLog;
use App\Services\Taxonomy\Taxonomy;
use Filament\Resources\Pages\CreateRecord;

class CreateTaxonomyTerm extends CreateRecord
{
    protected static string $resource = TaxonomyTermResource::class;

    protected function afterCreate(): void
    {
        Taxonomy::flush();
        AuditLog::record(
            'taxonomy.term_created',
            ['type' => $this->record->type, 'value' => $this->record->value, 'vendor' => $this->record->vendor],
            'TaxonomyTerm',
            (string) $this->record->id,
        );
    }
}
