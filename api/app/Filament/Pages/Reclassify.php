<?php

namespace App\Filament\Pages;

use App\Jobs\ReclassifyKb;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

/**
 * Run scoped, queued re-classifications of KB sources (LLM re-tagging) and document the CLI workflow.
 * Full runs belong on the CLI (no HTTP/worker timeout); this page is for bounded, scoped runs + the guide.
 */
class Reclassify extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';

    protected static string | UnitEnum | null $navigationGroup = 'Knowledge Base';

    protected static ?string $navigationLabel = 'Re-classify';

    protected static ?string $title = 'Re-classify the knowledge base';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.reclassify';

    /** @var array<string,mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['only' => null, 'limit' => 50, 'sleep' => 3, 'dry_run' => true]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Run a scoped re-classification')
                    ->description('Queued in the background — one Claude call per source. Keep it scoped; for a full KB run use the CLI (see the guide below).')
                    ->icon('heroicon-o-play')
                    ->schema([
                        TextInput::make('only')
                            ->label('Only sources whose path contains')
                            ->placeholder('e.g. customer, informatica, databricks — leave blank for all (capped by limit)'),
                        TextInput::make('limit')
                            ->label('Max sources')
                            ->numeric()
                            ->default(50)
                            ->minValue(1)
                            ->maxValue(100)
                            ->helperText('Capped at 100 so the run finishes within the worker timeout. Use the CLI for larger runs.'),
                        TextInput::make('sleep')
                            ->label('Pause between calls (seconds)')
                            ->numeric()
                            ->default(3)
                            ->minValue(0)
                            ->maxValue(30),
                        Toggle::make('dry_run')
                            ->label('Dry run (preview changes, write nothing)')
                            ->default(true),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function run(): void
    {
        $data = $this->form->getState();
        ReclassifyKb::dispatch(
            only: $data['only'] ?: null,
            limit: min(100, max(1, (int) ($data['limit'] ?? 50))),
            sleep: max(0, (int) ($data['sleep'] ?? 3)),
            dryRun: (bool) ($data['dry_run'] ?? true),
        );

        Notification::make()
            ->title($data['dry_run'] ?? true ? 'Dry-run queued' : 'Re-classification queued')
            ->body('Running in the background — results appear under "Recent runs" when it finishes (refresh the page).')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revert')
                ->label('Revert last re-classify')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Restores source + chunk tags from the most recent applied re-classification snapshot.')
                ->action(function () {
                    Artisan::call('kb:reclassify', ['--revert' => true]);
                    Notification::make()->title('Revert complete')->body(trim(Artisan::output()) ?: 'Done.')->success()->send();
                }),
        ];
    }

    /** Recent admin-triggered runs (with captured output). */
    public function recentRuns(): \Illuminate\Support\Collection
    {
        return AuditLog::where('action', 'kb.reclassify_run')->latest('created_at')->limit(5)->get();
    }

    /** The most recent applied (revertable) re-classification, if any. */
    public function lastApplied(): ?AuditLog
    {
        return AuditLog::where('action', 'kb.reclassified')->latest('created_at')->first();
    }
}
