<x-filament-panels::page>
    {{-- Run panel --}}
    <form wire:submit="run">
        {{ $this->form }}
        <div class="mt-4 flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-sparkles">
                Run re-classification
            </x-filament::button>
        </div>
    </form>

    {{-- Recent runs --}}
    <x-filament::section icon="heroicon-o-clock">
        <x-slot name="heading">Recent runs</x-slot>
        <x-slot name="description">Background runs triggered here, newest first.</x-slot>

        @php($runs = $this->recentRuns())
        @php($last = $this->lastApplied())

        @if ($last)
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                Last <strong>applied</strong> re-classification:
                {{ $last->meta['count'] ?? 0 }} source(s) on {{ $last->created_at?->diffForHumans() }} —
                revertable via the button above.
            </p>
        @endif

        @forelse ($runs as $run)
            <div class="mb-3 rounded-lg border border-gray-200 dark:border-white/10 p-3">
                <div class="flex items-center gap-3 text-sm">
                    <x-filament::badge :color="($run->meta['dry_run'] ?? false) ? 'gray' : 'success'">
                        {{ ($run->meta['dry_run'] ?? false) ? 'dry run' : 'applied' }}
                    </x-filament::badge>
                    <span class="font-mono text-xs text-gray-500">
                        only: {{ $run->meta['only'] ?? '—' }} · limit: {{ $run->meta['limit'] ?? '—' }}
                    </span>
                    <span class="ml-auto text-xs text-gray-400">{{ $run->created_at?->diffForHumans() }}</span>
                </div>
                @if (!empty($run->meta['output']))
                    <pre class="mt-2 max-h-60 overflow-auto whitespace-pre-wrap rounded bg-gray-50 dark:bg-white/5 p-2 text-xs leading-5 text-gray-600 dark:text-gray-300">{{ $run->meta['output'] }}</pre>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">No runs yet.</p>
        @endforelse
    </x-filament::section>

    {{-- The "little wiki" guide --}}
    <x-filament::section icon="heroicon-o-book-open" collapsible>
        <x-slot name="heading">How re-classification works — and when to use it</x-slot>
        <x-slot name="description">Read this before running a full re-classification.</x-slot>

        <div class="prose prose-sm dark:prose-invert max-w-none text-sm leading-6 text-gray-600 dark:text-gray-300 space-y-4">
            <p>
                <strong>What it does.</strong> Re-classification re-reads each source with the LLM classifier and
                re-derives its isolation tags — <code>mdm_vendor</code>, <code>product</code>, <code>domain</code>,
                <code>extension</code> (industry/vertical), and <code>financial_model</code> — then writes them to the
                source <em>and its chunks</em>. It’s how we fix cross-domain and vertical-edition contamination
                (e.g. Supplier/Insurance content bleeding into a Customer&nbsp;360 answer) and surface mis-tagged
                vendor content.
            </p>

            <div>
                <strong>Run it from the command line</strong> (recommended for anything large — no HTTP/worker timeout):
                <pre class="mt-1 rounded bg-gray-900 p-3 text-xs leading-5 text-gray-100 overflow-auto"><code># Preview every change without writing anything
php artisan kb:reclassify --dry-run

# Preview / apply a scoped subset (path contains "customer")
php artisan kb:reclassify --only=customer --dry-run
php artisan kb:reclassify --only=customer

# Bound a run + pace the LLM calls (provider rate limits)
php artisan kb:reclassify --limit=50 --sleep=3

# Undo the most recent applied run
php artisan kb:reclassify --revert</code></pre>
                <p class="mt-1 text-xs text-gray-400">The Run panel above does the same thing, queued and capped at 100 sources.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <strong class="text-gray-700 dark:text-gray-200">Pros</strong>
                    <ul class="mt-1 list-disc pl-5 space-y-1">
                        <li>Fixes cross-domain / vertical-edition contamination.</li>
                        <li>Re-derives product / domain / extension after taxonomy changes.</li>
                        <li>Surfaces mis-classified vendor content.</li>
                        <li>Reversible — snapshots tags, undo with <code>--revert</code>.</li>
                    </ul>
                </div>
                <div>
                    <strong class="text-gray-700 dark:text-gray-200">Cons</strong>
                    <ul class="mt-1 list-disc pl-5 space-y-1">
                        <li>One Claude call per source — token cost + time, rate-limited.</li>
                        <li>Non-deterministic; can mis-tag when the model is unsure.</li>
                        <li>Heavy across the whole KB — run from the CLI, not the UI.</li>
                    </ul>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <strong class="text-gray-700 dark:text-gray-200">When to run it</strong>
                    <ul class="mt-1 list-disc pl-5 space-y-1">
                        <li>After adding a new vendor / domain / extension to the taxonomy.</li>
                        <li>After a large or messy import where auto-tags look off.</li>
                        <li>To fix a specific contamination bug — scope it with <code>--only</code>.</li>
                    </ul>
                </div>
                <div>
                    <strong class="text-gray-700 dark:text-gray-200">When <em>not</em> to</strong>
                    <ul class="mt-1 list-disc pl-5 space-y-1">
                        <li>Routinely — ingest + path-classifier + import overrides already tag correctly.</li>
                        <li>After a re-crawl or <code>kb:refetch-urls</code> — those preserve tags.</li>
                        <li>Whole-KB from the UI — use the CLI to avoid timeouts.</li>
                    </ul>
                </div>
            </div>

            <p class="text-xs text-gray-400">
                Always <code>--dry-run</code> first to review the diff. If an applied run looks wrong, use
                <strong>Revert last re-classify</strong> (above) or <code>php artisan kb:reclassify --revert</code>.
            </p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
