<?php

namespace App\Providers;

use App\Services\Embeddings\Embedder;
use App\Services\Embeddings\FakeEmbedder;
use App\Services\Embeddings\SidecarEmbedder;
use App\Services\Embeddings\VoyageEmbedder;
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
        //
    }
}
