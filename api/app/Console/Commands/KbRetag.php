<?php

namespace App\Console\Commands;

use App\Models\Chunk;
use App\Models\Source;
use Illuminate\Console\Command;

/**
 * Bulk re-tag sources (and their chunks) whose path matches a filename substring — for
 * correcting mis-tagged uploads, e.g. `kb:retag --where=dgc- --vendor=informatica
 * --clear-platform --domain=data-governance --product="CDGC"`.
 */
class KbRetag extends Command
{
    protected $signature = 'kb:retag
        {--where= : substring to match in the source path (e.g. a filename prefix)}
        {--vendor= : set mdm_vendor}
        {--platform= : set data_platform}
        {--clear-platform : set data_platform to NULL}
        {--domain= : set domain}
        {--product= : set product}
        {--scope= : set scope}';

    protected $description = 'Bulk re-tag sources and their chunks matching a filename substring.';

    public function handle(): int
    {
        $where = (string) $this->option('where');
        if ($where === '') {
            $this->error('--where is required.');

            return self::FAILURE;
        }

        $updates = [];
        foreach (['vendor' => 'mdm_vendor', 'domain' => 'domain', 'product' => 'product', 'scope' => 'scope'] as $opt => $col) {
            $v = $this->option($opt);
            if ($v !== null && $v !== '') {
                $updates[$col] = $v;
            }
        }
        if ($this->option('clear-platform')) {
            $updates['data_platform'] = null;
        } elseif ($this->option('platform')) {
            $updates['data_platform'] = $this->option('platform');
        }

        if (empty($updates)) {
            $this->error('Nothing to set — provide at least one of --vendor/--platform/--clear-platform/--domain/--product/--scope.');

            return self::FAILURE;
        }

        $like = '%'.$where.'%';
        $srcCount = Source::where('path', 'like', $like)->count();
        if ($srcCount === 0) {
            $this->warn("No sources match '{$where}'.");

            return self::SUCCESS;
        }

        Source::where('path', 'like', $like)->update($updates);
        $chunkCount = Chunk::where('source_path', 'like', $like)->update($updates);

        $this->info("Re-tagged {$srcCount} sources + {$chunkCount} chunks matching '{$where}' → ".json_encode($updates));

        return self::SUCCESS;
    }
}
