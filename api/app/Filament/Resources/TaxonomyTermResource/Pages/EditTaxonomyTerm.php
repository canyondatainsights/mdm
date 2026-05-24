<?php

namespace App\Filament\Resources\TaxonomyTermResource\Pages;

use App\Filament\Resources\TaxonomyTermResource;
use App\Models\AuditLog;
use App\Services\Taxonomy\Taxonomy;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTaxonomyTerm extends EditRecord
{
    protected static string $resource = TaxonomyTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(fn () => Taxonomy::flush()),
        ];
    }

    protected function afterSave(): void
    {
        Taxonomy::flush();
        AuditLog::record(
            'taxonomy.term_updated',
            ['type' => $this->record->type, 'value' => $this->record->value, 'vendor' => $this->record->vendor],
            'TaxonomyTerm',
            (string) $this->record->id,
        );
    }
}
