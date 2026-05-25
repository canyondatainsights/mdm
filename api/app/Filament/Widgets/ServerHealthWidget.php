<?php

namespace App\Filament\Widgets;

use App\Services\Embeddings\Embedder;
use App\Services\Embeddings\SidecarEmbedder;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
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
            $this->cpu(),
            $this->memory(),
            $this->disk(),
        ];
    }

    private function cpu(): Stat
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        if (! $load) {
            return Stat::make('CPU load', 'n/a')->descriptionIcon('heroicon-m-cpu-chip')->color('gray');
        }
        $one = round((float) $load[0], 2);
        $cores = $this->cpuCores();
        $pct = $cores > 0 ? (int) round($one / $cores * 100) : null;
        $color = $pct === null ? 'gray' : ($pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success'));

        return Stat::make('CPU load', $pct !== null ? $pct.'%' : (string) $one)
            ->description($cores > 0 ? "load {$one} · {$cores} cores" : "1-min load {$one}")
            ->descriptionIcon('heroicon-m-cpu-chip')
            ->color($color);
    }

    private function memory(): Stat
    {
        $m = $this->systemMemory();
        if (! $m || $m['total'] <= 0) {
            return Stat::make('Memory', 'n/a')->descriptionIcon('heroicon-m-circle-stack')->color('gray');
        }
        $pct = (int) round($m['used'] / $m['total'] * 100);
        $color = $pct >= 90 ? 'danger' : ($pct >= 80 ? 'warning' : 'success');

        return Stat::make('Memory', $pct.'%')
            ->description($this->humanBytes($m['used']).' / '.$this->humanBytes($m['total']).' used')
            ->descriptionIcon('heroicon-m-circle-stack')
            ->color($color);
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

        // Stalled = a job has been reserved (in-flight) longer than the worker timeout — i.e. stuck or
        // the worker died mid-job. A deep but moving queue of pending jobs is NOT stalled.
        $oldestReserved = DB::table('jobs')->whereNotNull('reserved_at')->min('reserved_at');
        $stalled = $oldestReserved && (now()->timestamp - (int) $oldestReserved) > 900;

        $color = $failed > 0 ? 'danger' : ($stalled ? 'warning' : 'success');
        $value = $failed > 0 ? "{$failed} failed" : ($pending > 0 ? "{$pending} queued" : 'Idle');
        $desc = "{$pending} pending · {$failed} failed".($stalled ? ' · a job is stuck' : '');

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

    /** Logical CPU count (Linux/macOS), 0 if undeterminable. */
    private function cpuCores(): int
    {
        if (! function_exists('shell_exec')) {
            return 0;
        }
        foreach (['nproc', 'sysctl -n hw.ncpu', 'getconf _NPROCESSORS_ONLN'] as $cmd) {
            $n = (int) trim((string) @shell_exec($cmd.' 2>/dev/null'));
            if ($n > 0) {
                return $n;
            }
        }

        return 0;
    }

    /**
     * Best-effort system memory: Linux /proc/meminfo, else macOS sysctl + vm_stat.
     *
     * @return array{used:int,total:int}|null
     */
    private function systemMemory(): ?array
    {
        if (is_readable('/proc/meminfo')) {
            $info = (string) file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)/', $info, $t) && preg_match('/MemAvailable:\s+(\d+)/', $info, $a)) {
                $total = (int) $t[1] * 1024;

                return ['used' => $total - ((int) $a[1] * 1024), 'total' => $total];
            }
        }

        if (! function_exists('shell_exec')) {
            return null;
        }
        $total = (int) trim((string) @shell_exec('sysctl -n hw.memsize 2>/dev/null'));
        $vm = (string) @shell_exec('vm_stat 2>/dev/null');
        if ($total > 0 && $vm !== '') {
            $pageSize = preg_match('/page size of (\d+)/', $vm, $ps) ? (int) $ps[1] : 4096;
            $freePages = 0;
            foreach (['Pages free', 'Pages inactive', 'Pages speculative'] as $k) {
                if (preg_match('/'.preg_quote($k, '/').':\s+(\d+)/', $vm, $m)) {
                    $freePages += (int) $m[1];
                }
            }

            return ['used' => max(0, $total - $freePages * $pageSize), 'total' => $total];
        }

        return null;
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
