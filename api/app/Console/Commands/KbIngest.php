<?php

namespace App\Console\Commands;

use App\Services\Kb\Ingestor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class KbIngest extends Command
{
    protected $signature = 'kb:ingest
        {--path= : Ingest a single file (relative to the KB root)}
        {--fresh : Clear all chunks and re-embed everything (use after switching embeddings driver)}';

    protected $description = 'Parse, chunk, embed, and index the knowledge base (wiki/ + raw/) into pgvector';

    public function handle(Ingestor $ingestor): int
    {
        $root = rtrim(config('mdm.kb_path'), '/');
        $this->info("KB root: {$root}");
        $this->info('Embedder: '.config('mdm.embeddings.driver').' ('.config('mdm.embeddings.dim').'d)');

        if ($this->option('fresh')) {
            // Drop all vectors and reset idempotency hashes so every file re-embeds.
            DB::table('chunks')->delete();
            DB::table('wiki_pages')->update(['content_hash' => null]);
            $this->warn('--fresh: cleared all chunks and reset wiki_pages.content_hash.');
        }

        if ($single = $this->option('path')) {
            $abs = $root.'/'.ltrim($single, '/');
            $kind = str_starts_with(ltrim($single, '/'), 'raw/') ? 'raw' : 'wiki';
            $r = $ingestor->ingestFile($abs, $kind, $root);
            $this->line(sprintf('  [%s] %s (%d chunks)', $r['status'], $r['path'], $r['chunks']));

            return self::SUCCESS;
        }

        $results = $ingestor->ingestAll(function ($r) {
            $this->line(sprintf('  [%-10s] %s (%d chunks)', $r['status'], $r['path'], $r['chunks']));
        });

        $indexed = collect($results)->where('status', 'indexed');
        $this->newLine();
        $this->info(sprintf(
            'Done. %d files indexed, %d unchanged, %d unsupported, %d total chunks.',
            $indexed->count(),
            collect($results)->where('status', 'unchanged')->count(),
            collect($results)->where('status', 'unsupported')->count(),
            $indexed->sum('chunks'),
        ));

        return self::SUCCESS;
    }
}
