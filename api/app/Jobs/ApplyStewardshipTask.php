<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\StewardshipTask;
use App\Services\Kb\Ingestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;

class ApplyStewardshipTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public StewardshipTask $task,
    ) {}

    public function handle(Ingestor $ingestor): void
    {
        $kbRoot = rtrim(config('mdm.kb_path'), '/');

        if ($this->task->type === 'adr') {
            $this->applyAdr($kbRoot);
        } else {
            $this->applyWikiEdit($kbRoot);
        }

        // Re-index the affected file.
        $abs = $kbRoot.'/'.$this->task->target_path;
        if (file_exists($abs)) {
            $kind = str_starts_with($this->task->target_path, 'wiki/') ? 'wiki' : 'raw';
            $ingestor->ingestFile($abs, $kind, $kbRoot);
        }

        // Git commit.
        $commitHash = $this->gitCommit($kbRoot);

        // Record in audit log.
        $this->task->update(['diff' => $this->task->diff ?: 'applied']);
        AuditLog::record(
            'stewardship.applied',
            [
                'task_id' => $this->task->id,
                'target_path' => $this->task->target_path,
                'type' => $this->task->type,
            ],
            'StewardshipTask',
            (string) $this->task->id,
            $commitHash,
        );
    }

    private function applyWikiEdit(string $kbRoot): void
    {
        $targetPath = $this->task->target_path;

        // If no target_path was set, derive one from the summary or create a new page.
        if (! $targetPath) {
            $targetPath = 'wiki/stewardship/'.now()->format('Y-m-d').'-task-'.$this->task->id.'.md';
            $this->task->update(['target_path' => $targetPath]);
        }

        $abs = $kbRoot.'/'.$targetPath;
        $dir = dirname($abs);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $existing = file_exists($abs) ? file_get_contents($abs) : '';

        if ($existing) {
            // Append the proposed content as a new section before the Revision log.
            $content = $this->insertBeforeRevisionLog($existing, $this->task->proposed_content);
        } else {
            // New page: write proposed content + a fresh Revision log.
            $content = $this->task->proposed_content."\n\n## Revision log\n\n| Date | Change |\n|---|---|\n| ".now()->format('Y-m-d')." | Created via stewardship task #{$this->task->id}. |\n";
        }

        // Append a Revision log entry for existing pages.
        if ($existing) {
            $content = $this->appendRevisionEntry($content, "Updated via stewardship task #{$this->task->id}.");
        }

        file_put_contents($abs, $content);

        // Update _CHANGELOG.md.
        $this->appendChangelog($kbRoot, $targetPath);
    }

    private function applyAdr(string $kbRoot): void
    {
        $adrDir = $kbRoot.'/wiki/09-decisions-adrs';
        if (! is_dir($adrDir)) {
            mkdir($adrDir, 0755, true);
        }

        // Find next ADR number.
        $existing = glob($adrDir.'/adr-*.md');
        $maxNum = 0;
        foreach ($existing as $f) {
            if (preg_match('/adr-(\d+)/', basename($f), $m)) {
                $maxNum = max($maxNum, (int) $m[1]);
            }
        }
        $num = str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(substr($this->task->summary, 0, 60)));
        $slug = trim($slug, '-');
        $filename = "adr-{$num}-{$slug}.md";
        $targetPath = "wiki/09-decisions-adrs/{$filename}";

        $this->task->update(['target_path' => $targetPath]);

        $content = "# ADR {$num}: {$this->task->summary}\n\n";
        $content .= "**Status:** Accepted\n";
        $content .= "**Date:** ".now()->format('Y-m-d')."\n";
        $content .= "**Proposed by:** ".($this->task->proposer?->name ?? 'Unknown')."\n\n";
        $content .= "## Context\n\n{$this->task->proposed_content}\n\n";
        $content .= "## Decision\n\nAs proposed in stewardship task #{$this->task->id}.\n\n";
        $content .= "## Revision log\n\n| Date | Change |\n|---|---|\n| ".now()->format('Y-m-d')." | ADR created via stewardship. |\n";

        file_put_contents($kbRoot.'/'.$targetPath, $content);

        $this->appendChangelog($kbRoot, $targetPath);
    }

    private function insertBeforeRevisionLog(string $content, string $insert): string
    {
        // Insert proposed content before the "## Revision log" heading.
        $marker = '## Revision log';
        $pos = stripos($content, $marker);
        if ($pos !== false) {
            return substr($content, 0, $pos)."\n".$insert."\n\n".substr($content, $pos);
        }

        // No Revision log section — just append.
        return $content."\n\n".$insert;
    }

    private function appendRevisionEntry(string $content, string $entry): string
    {
        // Find the last row of the Revision log table and append after it.
        $date = now()->format('Y-m-d');
        $row = "| {$date} | {$entry} |";

        // Find the Revision log table — append after the last pipe-delimited row.
        if (preg_match('/## Revision log.*?\n(\|.+\|[\s\S]*?)(\n(?:##|\z))/i', $content, $m, PREG_OFFSET_CAPTURE)) {
            $tableEnd = $m[1][1] + strlen($m[1][0]);

            return substr($content, 0, $tableEnd)."\n".$row.substr($content, $tableEnd);
        }

        return $content."\n".$row."\n";
    }

    private function appendChangelog(string $kbRoot, string $targetPath): void
    {
        $changelogPath = $kbRoot.'/_CHANGELOG.md';
        if (! file_exists($changelogPath)) {
            return;
        }

        $date = now()->format('Y-m-d');
        $summary = $this->task->summary;
        $entry = "\n## {$date} — Stewardship #{$this->task->id}\n\n- Applied: `{$targetPath}` — {$summary}\n- Reviewer: ".($this->task->reviewer?->name ?? 'system')."\n";

        // Insert after the first heading block (after the initial "# Knowledge Base Changelog" paragraph).
        $changelog = file_get_contents($changelogPath);
        $secondHeading = strpos($changelog, "\n## ", 1);
        if ($secondHeading !== false) {
            $changelog = substr($changelog, 0, $secondHeading)."\n".$entry.substr($changelog, $secondHeading);
        } else {
            $changelog .= "\n".$entry;
        }

        file_put_contents($changelogPath, $changelog);
    }

    private function gitCommit(string $kbRoot): ?string
    {
        $message = "stewardship: {$this->task->type} #{$this->task->id} — {$this->task->summary}";
        $author = $this->task->proposer?->name ?? 'system';
        $email = $this->task->proposer?->email ?? 'system@mdm.local';

        // Scope staging to the KB directory: kb/ lives inside the monorepo, so a
        // bare `git add -A` would sweep unrelated repo changes into the audit commit.
        $result = Process::path($kbRoot)
            ->run("git add -A -- . && git commit --author=\"{$author} <{$email}>\" -m ".escapeshellarg($message).' 2>&1');

        if ($result->successful()) {
            $hashResult = Process::path($kbRoot)->run('git rev-parse HEAD');

            return trim($hashResult->output());
        }

        return null;
    }
}
