<?php

namespace App\Filament\Pages;

use App\Jobs\ClassifyAndIngestUpload;
use App\Models\AuditLog;
use App\Models\Chunk;
use App\Models\Source;
use App\Services\Kb\UploadTagger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Drop many PDFs → each is classified by Claude and ingested in the background (one queued job per
 * file, coordinated by a Bus batch for progress). Duplicates are skipped; new subjects the classifier
 * proposes are collected for steward approval (then a one-click retag), unless auto-create is on.
 */
class BatchImport extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-up-on-square-stack';

    protected static string | UnitEnum | null $navigationGroup = 'Knowledge Base';

    protected static ?string $navigationLabel = 'Batch import';

    protected static ?string $title = 'Batch import & auto-classify';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.batch-import';

    /** @var array<string,mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['files' => [], 'auto_create_subjects' => false]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Drop your documents')
                    ->description('Each file is classified by Claude and ingested in the background. Duplicates are skipped automatically. Track progress below.')
                    ->icon('heroicon-o-document-arrow-up')
                    ->schema([
                        FileUpload::make('files')
                            ->label('Documents (PDF / MD / TXT / …)')
                            ->multiple()
                            ->preserveFilenames()
                            ->disk('local')
                            ->directory('kb-batch-tmp')
                            ->acceptedFileTypes([
                                'application/pdf', 'text/plain', 'text/markdown', 'text/csv',
                                'application/json', 'application/xml', 'text/xml', 'application/octet-stream',
                            ])
                            ->maxSize(131072)
                            ->columnSpanFull(),
                        Toggle::make('auto_create_subjects')
                            ->label('Auto-create new subjects the classifier proposes')
                            ->helperText('Off (recommended): proposed subjects are collected below for your approval. On: created automatically — faster, but may add near-duplicate subjects.')
                            ->default(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function import(): void
    {
        $data = $this->form->getState();
        $files = (array) ($data['files'] ?? []);
        $root = rtrim(config('mdm.kb_path'), '/');
        $auto = (bool) ($data['auto_create_subjects'] ?? false);
        $uid = auth()->id();

        $jobs = [];
        foreach ($files as $stored) {
            $abs = Storage::disk('local')->path($stored);
            if (is_file($abs)) {
                $jobs[] = new ClassifyAndIngestUpload($abs, basename((string) $stored), $root, $uid, $auto);
            }
        }
        if (empty($jobs)) {
            Notification::make()->title('Add some files first')->warning()->send();

            return;
        }

        $batch = Bus::batch($jobs)->name('pdf-batch')->allowFailures()->dispatch();
        AuditLog::record('batch.import', ['batch_id' => $batch->id, 'count' => count($jobs), 'auto_subjects' => $auto]);

        $this->form->fill(['files' => [], 'auto_create_subjects' => $auto]);
        Notification::make()
            ->title('Batch import started')
            ->body(count($jobs).' file(s) queued. Progress updates below (the page auto-refreshes).')
            ->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addSubjects')
                ->label('Approve proposed subjects + retag')
                ->icon('heroicon-o-check-badge')
                ->color('primary')
                ->visible(fn () => $this->proposedSubjects()->isNotEmpty())
                ->requiresConfirmation()
                ->modalDescription('Adds every proposed subject from the latest batch to the taxonomy and retags the documents that proposed them. No extra LLM calls.')
                ->action(fn () => $this->approveProposedSubjects()),
        ];
    }

    /** The latest batch's live progress, or null if none yet. */
    public function latestBatch(): ?array
    {
        $log = AuditLog::where('action', 'batch.import')->latest('created_at')->first();
        if (! $log) {
            return null;
        }
        $id = $log->meta['batch_id'] ?? null;
        $b = $id ? Bus::findBatch($id) : null;
        $ingested = AuditLog::where('action', 'batch.ingested')->whereJsonContains('meta->batch_id', $id)->count();
        $skipped = AuditLog::where('action', 'batch.skipped')->whereJsonContains('meta->batch_id', $id)->count();

        return [
            'id' => $id,
            'created' => $log->created_at,
            'total' => $b?->totalJobs ?? ($log->meta['count'] ?? 0),
            'progress' => $b ? $b->progress() : 100,
            'pending' => $b?->pendingJobs ?? 0,
            'failed' => $b?->failedJobs ?? 0,
            'finished' => $b ? $b->finished() : true,
            'ingested' => $ingested,
            'skipped' => $skipped,
        ];
    }

    /** Proposed (un-created) new subjects from the latest batch, deduped with counts + paths. */
    public function proposedSubjects(): Collection
    {
        $id = AuditLog::where('action', 'batch.import')->latest('created_at')->first()?->meta['batch_id'] ?? null;
        if (! $id) {
            return collect();
        }

        return AuditLog::where('action', 'batch.proposed_subject')->whereJsonContains('meta->batch_id', $id)->get()
            ->groupBy(fn ($r) => $r->meta['value'])
            ->map(fn ($g) => [
                'value' => $g->first()->meta['value'],
                'label' => $g->first()->meta['label'] ?? $g->first()->meta['value'],
                'count' => $g->count(),
                'paths' => $g->pluck('meta.path')->filter()->values()->all(),
            ])->values();
    }

    /** Add each proposed subject to the taxonomy and retag the documents that proposed it (no LLM). */
    protected function approveProposedSubjects(): void
    {
        $tagger = app(UploadTagger::class);
        $added = 0;
        $retagged = 0;

        foreach ($this->proposedSubjects() as $sub) {
            $tagger->persistNewSubjects(null, [['new_subject' => ['value' => $sub['value'], 'label' => $sub['label']]]]);
            $added++;
            foreach (Source::whereIn('path', $sub['paths'])->get() as $s) {
                $s->update(['domain' => $sub['value'], 'needs_metadata' => false]);
                Chunk::where('source_id', $s->id)->update(['domain' => $sub['value']]);
                $retagged++;
            }
        }

        Notification::make()
            ->title("Added {$added} subject(s), retagged {$retagged} document(s)")
            ->success()->send();
    }
}
