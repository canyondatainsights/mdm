<?php

namespace App\Filament\Widgets;

use App\Services\Embeddings\Embedder;
use App\Services\Embeddings\SidecarEmbedder;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Live server health — database, embeddings sidecar, queue/worker, and disk. Polls every 30s. */
class ServerHealthWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected ?string $heading = 'Server health';

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            $this->database(),
            $this->embeddings(),
            $this->queue(),
            $this->disk(),
        ];
    }

    private function database(): Stat
    {
        try {
            DB::select('select 1');

            return Stat::make('Database', 'Connected')
                ->description('PostgreSQL + pgvector')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('success');
        } catch (\Throwable $e) {
            return Stat::make('Database', 'Down')
                ->description(Str::limit($e->getMessage(), 40))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger');
        }
    }

    private function embeddings(): Stat
    {
        $emb = app(Embedder::class);

        if ($emb instanceof SidecarEmbedder) {
            $ok = $emb->health();

            return Stat::make('Embeddings', $ok ? 'Online' : 'Down')
                ->description('Sidecar · '.config('mdm.embeddings.sidecar.url'))
                ->descriptionIcon($ok ? 'heroicon-m-cpu-chip' : 'heroicon-m-exclamation-triangle')
                ->color($ok ? 'success' : 'danger');
        }

        return Stat::make('Embeddings', ucfirst((string) config('mdm.embeddings.driver')))
            ->description('Driver')
            ->descriptionIcon('heroicon-m-cpu-chip')
            ->color('gray');
    }

    private function queue(): Stat
    {
        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();

        // Oldest pending job age (jobs.created_at is a unix timestamp); a large value while jobs sit
        // pending suggests the worker is stalled or down.
        $oldest = DB::table('jobs')->min('created_at');
        $stalled = $pending > 0 && $oldest && Carbon::createFromTimestamp($oldest)->diffInMinutes(now()) > 10;

        $color = $failed > 0 ? 'danger' : ($stalled ? 'warning' : 'success');
        $value = $failed > 0 ? "{$failed} failed" : ($pending > 0 ? "{$pending} queued" : 'Idle');
        $desc = "{$pending} pending · {$failed} failed".($stalled ? ' · worker may be stalled' : '');

        return Stat::make('Queue', $value)
            ->description($desc)
            ->descriptionIcon('heroicon-m-queue-list')
            ->color($color);
    }

    private function disk(): Stat
    {
        $path = rtrim((string) config('mdm.kb_path'), '/');
        $free = (float) (@disk_free_space($path) ?: 0);
        $total = (float) (@disk_total_space($path) ?: 1);
        $pctFree = (int) round($free / max($total, 1) * 100);
        $color = $pctFree < 10 ? 'danger' : ($pctFree < 20 ? 'warning' : 'success');

        return Stat::make('Disk free', $this->humanBytes($free))
            ->description($pctFree.'% free of '.$this->humanBytes($total))
            ->descriptionIcon('heroicon-m-server')
            ->color($color);
    }

    private function humanBytes(float $b): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($b >= 1024 && $i < count($units) - 1) {
            $b /= 1024;
            $i++;
        }

        return round($b, 1).' '.$units[$i];
    }
}
