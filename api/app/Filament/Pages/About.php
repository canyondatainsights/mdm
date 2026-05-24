<?php

namespace App\Filament\Pages;

use App\Services\Kb\KbStats;
use BackedEnum;
use Composer\InstalledVersions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** Reference page: the full Sidecar stack — backend, frontend, AI/embeddings — with live versions. */
class About extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-information-circle';

    protected static string | UnitEnum | null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'About';

    protected static ?string $title = 'About Sidecar';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.about';

    protected function getViewData(): array
    {
        $kb = app(KbStats::class)->totals();
        $web = rescue(fn () => json_decode((string) file_get_contents(base_path('../web/package.json')), true), [], false) ?: [];
        $webVer = fn (string $name) => $web['dependencies'][$name] ?? $web['devDependencies'][$name] ?? '—';

        $pg = rescue(fn () => (string) DB::selectOne('select version() as v')->v, null, false);
        $pgVer = $pg && preg_match('/PostgreSQL\s+([\d.]+)/', $pg, $m) ? $m[1] : '—';

        return [
            'groups' => [
                [
                    'heading' => 'Backend',
                    'description' => 'PHP API, admin, and retrieval pipeline.',
                    'icon' => 'heroicon-o-server-stack',
                    'items' => [
                        ['Laravel', app()->version()],
                        ['PHP', PHP_VERSION],
                        ['Filament (admin)', $this->pkg('filament/filament')],
                        ['Laravel Sanctum (auth)', $this->pkg('laravel/sanctum')],
                        ['PostgreSQL', $pgVer],
                        ['pgvector', $this->pkg('pgvector/pgvector').' · '.config('mdm.embeddings.dim').'-dim vectors'],
                        ['Queue', config('queue.default').' connection'],
                        ['PhpSpreadsheet (xlsx export)', $this->pkg('phpoffice/phpspreadsheet')],
                    ],
                ],
                [
                    'heading' => 'Frontend',
                    'description' => 'Next.js chat UI, streamed over SSE.',
                    'icon' => 'heroicon-o-window',
                    'items' => [
                        ['Next.js', $webVer('next')],
                        ['React', $webVer('react')],
                        ['TypeScript', $webVer('typescript')],
                        ['Icons', 'lucide-react '.$webVer('lucide-react')],
                        ['Styling', 'CSS-in-JS · OKLCH design tokens'],
                        ['Transport', 'Server-Sent Events (fetch ReadableStream)'],
                    ],
                ],
                [
                    'heading' => 'AI & retrieval',
                    'description' => 'Models, embeddings, and vendor-isolated RAG.',
                    'icon' => 'heroicon-o-cpu-chip',
                    'items' => [
                        ['LLM provider', 'Anthropic Claude (via Prism '.$this->pkg('prism-php/prism').')'],
                        ['Active model', app(\App\Services\SettingsService::class)->anthropicModel()],
                        ['Embeddings', 'BAAI/bge-large-en-v1.5 (local sidecar, '.config('mdm.embeddings.dim').'-dim)'],
                        ['Embeddings driver', config('mdm.embeddings.driver')],
                        ['Retrieval', 'pgvector cosine · top-k '.config('mdm.retrieval.top_k')],
                        ['Permissions', 'spatie/laravel-permission '.$this->pkg('spatie/laravel-permission')],
                    ],
                ],
                [
                    'heading' => 'Knowledge base',
                    'description' => 'Current coverage.',
                    'icon' => 'heroicon-o-circle-stack',
                    'items' => [
                        ['Sources', number_format($kb['sources']).' ('.$kb['approved'].' approved)'],
                        ['Wiki pages', number_format($kb['wiki_pages'])],
                        ['Chunks', number_format($kb['chunks'])],
                    ],
                ],
            ],
        ];
    }

    /** Installed composer package version, or "—" if absent. */
    private function pkg(string $name): string
    {
        return rescue(fn () => InstalledVersions::getPrettyVersion($name), null, false) ?? '—';
    }
}
