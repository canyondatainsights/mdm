<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Control the queue workers from the admin: restart them (graceful — finish the current job, the
 * supervisor respawns with fresh code), pause/resume processing (a flag the workers honor, so they
 * idle instead of being killed), and retry failed jobs.
 */
class Workers extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string | UnitEnum | null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Queue & workers';

    protected static ?string $title = 'Queue & workers';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.workers';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['Admin']);
    }

    /** Flag file that pauses processing while present (checked by the Looping hook in AppServiceProvider). */
    protected function pauseFlag(): string
    {
        return storage_path('framework/queue-paused');
    }

    public function isPaused(): bool
    {
        return is_file($this->pauseFlag());
    }

    public function pending(): int
    {
        return DB::table('jobs')->count();
    }

    public function failed(): int
    {
        return DB::table('failed_jobs')->count();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('restart')
                ->label('Restart workers')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription('Signals workers to finish their current job and exit; the supervisor respawns them with fresh code. Safe — in-flight jobs complete first.')
                ->action(function () {
                    Artisan::call('queue:restart');
                    AuditLog::record('queue.restart', []);
                    Notification::make()->title('Restart signal sent')->body('Workers reload on their next loop.')->success()->send();
                }),

            Action::make('toggle')
                ->label(fn () => $this->isPaused() ? 'Resume processing' : 'Pause processing')
                ->icon(fn () => $this->isPaused() ? 'heroicon-o-play' : 'heroicon-o-pause')
                ->color(fn () => $this->isPaused() ? 'success' : 'warning')
                ->requiresConfirmation()
                ->modalDescription(fn () => $this->isPaused()
                    ? 'Workers will resume fetching jobs.'
                    : 'Workers stay alive but stop fetching new jobs until resumed; a job already running finishes.')
                ->action(function () {
                    if ($this->isPaused()) {
                        @unlink($this->pauseFlag());
                        AuditLog::record('queue.resumed', []);
                        Notification::make()->title('Processing resumed')->success()->send();
                    } else {
                        @touch($this->pauseFlag());
                        AuditLog::record('queue.paused', []);
                        Notification::make()->title('Processing paused')->body('Workers are idle until you resume.')->warning()->send();
                    }
                }),

            Action::make('retryFailed')
                ->label('Retry failed jobs')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn () => $this->failed() > 0)
                ->requiresConfirmation()
                ->action(function () {
                    Artisan::call('queue:retry', ['id' => ['all']]);
                    AuditLog::record('queue.retry_failed', ['count' => $this->failed()]);
                    Notification::make()->title('Failed jobs requeued')->success()->send();
                }),
        ];
    }
}
