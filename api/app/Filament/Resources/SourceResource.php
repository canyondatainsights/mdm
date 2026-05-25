<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SourceResource\Pages;
use App\Jobs\IngestUploadedFile;
use App\Models\AuditLog;
use App\Models\Chunk;
use App\Models\Source;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SourceResource extends Resource
{
    protected static ?string $model = Source::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static string | \UnitEnum | null $navigationGroup = 'Knowledge Base';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Source Details')->schema([
                Infolists\Components\TextEntry::make('title'),
                Infolists\Components\TextEntry::make('path')->copyable(),
                Infolists\Components\TextEntry::make('doc_type')->badge(),
                Infolists\Components\TextEntry::make('pages'),
                Infolists\Components\IconEntry::make('approved')->boolean(),
                Infolists\Components\TextEntry::make('uploader.name')->label('Uploaded by')->placeholder('system'),
                Infolists\Components\TextEntry::make('created_at')->dateTime(),
            ])->columns(2),
            Section::make('Isolation Tags')->schema([
                Infolists\Components\TextEntry::make('mdm_vendor')->placeholder('—'),
                Infolists\Components\TextEntry::make('data_platform')->placeholder('—'),
                Infolists\Components\TextEntry::make('financial_model')->placeholder('—'),
                Infolists\Components\TextEntry::make('domain')->placeholder('—'),
                Infolists\Components\TextEntry::make('scope')->placeholder('—'),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('5s')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('doc_type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('mdm_vendor')
                    ->label('Vendor')
                    ->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('data_platform')
                    ->label('Platform')
                    ->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('domain')
                    ->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('product')
                    ->placeholder('—')
                    ->description(fn (Source $r) => $r->product_version)->sortable(),
                Tables\Columns\TextColumn::make('ingest_status')
                    ->label('Ingestion')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state) => match ($state) {
                        'ready' => 'success',
                        'processing' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('needs_metadata')
                    ->label('Needs tags')
                    ->boolean()
                    ->sortable()
                    ->trueIcon('heroicon-o-exclamation-triangle')->trueColor('warning')
                    ->falseIcon('heroicon-o-check-circle')->falseColor('success'),
                Tables\Columns\IconColumn::make('approved')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('needs_metadata')->label('Needs metadata'),
                Tables\Filters\TernaryFilter::make('approved'),
                Tables\Filters\SelectFilter::make('doc_type')
                    ->options(fn () => Source::distinct()->whereNotNull('doc_type')->pluck('doc_type', 'doc_type')->toArray()),
                Tables\Filters\SelectFilter::make('mdm_vendor')
                    ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('mdm_vendor'))->mapWithKeys(fn ($v) => [$v => $v])->toArray()),
                Tables\Filters\SelectFilter::make('data_platform')
                    ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('data_platform'))->mapWithKeys(fn ($v) => [$v => $v])->toArray()),
                Tables\Filters\SelectFilter::make('domain')
                    ->label('Subject / domain')
                    ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('domain'))->mapWithKeys(fn ($v) => [$v => $v])->toArray()),
                Tables\Filters\SelectFilter::make('ingest_status')
                    ->label('Ingestion')
                    ->options(['ready' => 'Ready', 'processing' => 'Processing', 'queued' => 'Queued', 'failed' => 'Failed']),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\Action::make('editMeta')
                    ->label('Edit metadata')
                    ->icon('heroicon-o-tag')
                    ->color(fn (Source $record) => $record->needs_metadata ? 'warning' : 'gray')
                    ->modalHeading('Source metadata')
                    ->modalDescription('Vendor + Product are required before this source is used in answers.')
                    ->fillForm(fn (Source $record) => [
                        'mdm_vendor' => $record->mdm_vendor,
                        'data_platform' => $record->data_platform,
                        'product' => $record->product,
                        'product_version' => $record->product_version,
                        'domain' => $record->domain,
                        'scope' => $record->scope,
                    ])
                    ->form([
                        Forms\Components\Select::make('mdm_vendor')->label('Vendor')
                            ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('mdm_vendor'))->mapWithKeys(fn ($v) => [$v => $v])->all())
                            ->live(),
                        Forms\Components\Select::make('data_platform')->label('Data platform')
                            ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('data_platform'))->mapWithKeys(fn ($v) => [$v => $v])->all()),
                        Forms\Components\TextInput::make('product')->maxLength(128)
                            ->datalist(fn (Get $get) => \App\Services\Taxonomy\Taxonomy::productsFor($get('mdm_vendor'))),
                        Forms\Components\TextInput::make('product_version')->label('Version')->maxLength(64),
                        Forms\Components\Select::make('domain')
                            ->options(fn () => collect(\App\Services\Taxonomy\Taxonomy::values('domain'))->mapWithKeys(fn ($v) => [$v => $v])->all()),
                        Forms\Components\Select::make('scope')->options(['vendor-specific' => 'Vendor-specific', 'neutral' => 'Neutral']),
                    ])
                    ->action(function (Source $record, array $data) {
                        $needs = empty($data['mdm_vendor']) || empty($data['product']);
                        $record->update(array_merge($data, ['needs_metadata' => $needs]));
                        // Propagate the tags to this source's chunks so retrieval/citations match.
                        Chunk::where('source_path', $record->path)->update([
                            'mdm_vendor' => $data['mdm_vendor'] ?: null,
                            'data_platform' => $data['data_platform'] ?: null,
                            'product' => $data['product'] ?: null,
                            'product_version' => $data['product_version'] ?: null,
                            'domain' => $data['domain'] ?: ($record->domain ?: 'general'),
                            'scope' => $data['scope'] ?: ($record->scope ?: 'vendor-specific'),
                        ]);
                        Notification::make()
                            ->title($needs ? 'Saved — still missing vendor/product (held from answers)' : 'Metadata saved — source is now live')
                            ->color($needs ? 'warning' : 'success')
                            ->send();
                    }),
                Actions\Action::make('toggleApproval')
                    ->icon(fn (Source $record) => $record->approved ? 'heroicon-o-x-mark' : 'heroicon-o-check')
                    ->color(fn (Source $record) => $record->approved ? 'danger' : 'success')
                    ->label(fn (Source $record) => $record->approved ? 'Revoke' : 'Approve')
                    ->requiresConfirmation()
                    ->action(fn (Source $record) => $record->update(['approved' => ! $record->approved])),
                Actions\Action::make('reingest')
                    ->label('Re-ingest')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription('Re-parse, re-chunk, and re-embed this document (e.g. after switching the embeddings driver).')
                    ->action(function (Source $record) {
                        $root = rtrim(config('mdm.kb_path'), '/');
                        IngestUploadedFile::dispatch(
                            $root.'/'.$record->path,
                            $root,
                            array_filter([
                                'mdm_vendor' => $record->mdm_vendor,
                                'data_platform' => $record->data_platform,
                                'financial_model' => $record->financial_model,
                                'domain' => $record->domain,
                                'scope' => $record->scope,
                                'product' => $record->product,
                                'product_version' => $record->product_version,
                            ], fn ($v) => $v !== null && $v !== ''),
                            $record->uploaded_by,
                        );
                        Notification::make()->title('Re-ingestion queued')->success()->send();
                    }),
                Actions\Action::make('deleteSource')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Removes the file from kb/raw, deletes its chunks, and removes the source record.')
                    ->action(function (Source $record) {
                        $abs = rtrim(config('mdm.kb_path'), '/').'/'.$record->path;
                        if (is_file($abs)) {
                            @unlink($abs);
                        }
                        // chunks cascade via the source_id FK (ON DELETE CASCADE).
                        $record->delete();
                        Notification::make()->title('Source deleted')->success()->send();
                    }),
            ])
            ->toolbarActions([
                Actions\BulkAction::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Approved sources become available to the assistant in answers.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records) {
                        $records->each->update(['approved' => true]);
                        AuditLog::record('source.approved', ['count' => $records->count(), 'paths' => $records->pluck('path')->all()]);
                        Notification::make()->title('Approved '.$records->count().' source(s)')->success()->send();
                    }),
                Actions\BulkAction::make('unapprove')
                    ->label('Unapprove')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Unapproved sources are immediately held out of answers.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records) {
                        $records->each->update(['approved' => false]);
                        AuditLog::record('source.unapproved', ['count' => $records->count(), 'paths' => $records->pluck('path')->all()]);
                        Notification::make()->title('Unapproved '.$records->count().' source(s)')->warning()->send();
                    }),
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSources::route('/'),
            'view' => Pages\ViewSource::route('/{record}'),
        ];
    }
}
