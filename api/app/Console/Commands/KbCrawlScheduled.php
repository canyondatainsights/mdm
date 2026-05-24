<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Crawler;
use App\Services\Kb\CrawlerService;
use Illuminate\Console\Command;

/**
 * Runs documentation crawlers whose per-crawler schedule (daily/weekly/monthly) is due. Registered to
 * run hourly in routes/console.php; the command itself decides which crawlers are due via Crawler::isDue().
 * Re-crawls are cheap — content_hash dedup skips unchanged pages.
 */
class KbCrawlScheduled extends Command
{
    protected $signature = 'crawlers:run-scheduled {--force : run every active scheduled crawler now, ignoring the due check}';

    protected $description = 'Run documentation crawlers whose schedule is due (daily/weekly/monthly).';

    public function handle(CrawlerService $service): int
    {
        $force = (bool) $this->option('force');
        $ran = 0;

        foreach (Crawler::where('active', true)->get() as $crawler) {
            $due = $force
                ? ! in_array($crawler->schedule, [null, '', 'off'], true)
                : $crawler->isDue();
            if (! $due) {
                continue;
            }

            $this->info("Crawling {$crawler->key} ({$crawler->schedule})…");
            $result = $service->crawl($crawler->key, $crawler->toProfile(), ['sleep' => 1]);
            $crawler->update(['last_run_at' => now()]);
            AuditLog::record('crawler.scheduled', [
                'crawler' => $crawler->key,
                'schedule' => $crawler->schedule,
                'matched' => $result['matched'],
                'queued' => $result['queued'],
            ]);
            $this->line("  matched {$result['matched']}, queued {$result['queued']}");
            $ran++;
        }

        $this->info($ran ? "Ran {$ran} scheduled crawler(s)." : 'No crawlers due.');

        return self::SUCCESS;
    }
}
