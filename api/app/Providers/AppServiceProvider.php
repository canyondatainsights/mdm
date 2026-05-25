<?php

namespace App\Providers;

use App\Services\Embeddings\Embedder;
use App\Services\Embeddings\FakeEmbedder;
use App\Services\Embeddings\SidecarEmbedder;
use App\Services\Embeddings\VoyageEmbedder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Embedder::class, function () {
            $cfg = config('mdm.embeddings');
            $dim = (int) $cfg['dim'];

            return match ($cfg['driver']) {
                'voyage' => new VoyageEmbedder(
                    $cfg['voyage']['key'] ?? throw new RuntimeException('VOYAGE_API_KEY is not set'),
                    $cfg['voyage']['model'],
                    $cfg['voyage']['url'],
                    $dim,
                ),
                'sidecar' => new SidecarEmbedder($cfg['sidecar']['url'], $dim),
                default => new FakeEmbedder($dim),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Admin-controlled pause: while the flag file exists, workers idle (returning false from a
        // Looping listener makes the daemon skip fetching) instead of being killed — works with the
        // self-healing worker loop. Toggled from Admin → System → Queue & workers.
        Queue::looping(fn () => is_file(storage_path('framework/queue-paused')) ? false : null);
    }
}
