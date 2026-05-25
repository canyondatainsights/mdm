<x-filament-panels::page>
    {{-- Upload + options --}}
    <form wire:submit="import">
        {{ $this->form }}
        <div class="mt-4 flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
                Import &amp; classify
            </x-filament::button>
        </div>
    </form>

    {{-- Progress + review (auto-refreshes only while a batch is actively running). --}}
    <div @if ($this->hasActiveBatch()) wire:poll.5s @endif class="space-y-6">
        @php($batches = $this->recentBatches())
        @if ($batches->isNotEmpty())
            <x-filament::section icon="heroicon-o-chart-bar">
                <x-slot name="heading">Batches ({{ $batches->where('finished', false)->count() }} running)</x-slot>
                <x-slot name="description">Running batches first, then most recent.</x-slot>

                <div class="space-y-4">
                    @foreach ($batches as $batch)
                        <div @class([
                            'rounded-lg border p-3',
                            'border-amber-300 dark:border-amber-500/40' => ! $batch['finished'],
                            'border-gray-200 dark:border-white/10' => $batch['finished'],
                        ])>
                            <div class="mb-2 flex flex-wrap items-center gap-3 text-sm">
                                <x-filament::badge :color="$batch['finished'] ? 'success' : 'warning'">
                                    {{ $batch['finished'] ? 'finished' : 'running' }}
                                </x-filament::badge>
                                <span class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {{ $batch['progress'] }}% ·
                                    {{ $batch['ingested'] }} ingested ·
                                    {{ $batch['skipped'] }} skipped (dupes) ·
                                    {{ $batch['failed'] }} failed ·
                                    {{ $batch['pending'] }} pending / {{ $batch['total'] }} total
                                </span>
                                <span class="ml-auto text-xs text-gray-400">{{ $batch['created']?->diffForHumans() }}</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                <div class="h-full rounded-full bg-primary-500 transition-all" style="width: {{ max(2, (int) $batch['progress']) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @php($proposed = $this->proposedSubjects())
        @if ($proposed->isNotEmpty())
            <x-filament::section icon="heroicon-o-light-bulb">
                <x-slot name="heading">Proposed new subjects ({{ $proposed->count() }})</x-slot>
                <x-slot name="description">
                    The classifier suggested these subjects, which aren’t in the taxonomy yet. Use
                    <strong>“Approve proposed subjects + retag”</strong> (top of page) to add them and retag the
                    documents that proposed them — no extra LLM calls.
                </x-slot>
                <div class="space-y-1.5">
                    @foreach ($proposed as $p)
                        <div class="flex items-center gap-3 text-sm">
                            <x-filament::badge color="info">{{ $p['label'] }}</x-filament::badge>
                            <span class="font-mono text-xs text-gray-500">{{ $p['value'] }}</span>
                            <span class="ml-auto text-xs text-gray-400">{{ $p['count'] }} doc(s)</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @php($log = $this->importLog())
        @if ($log->isNotEmpty())
            @php($c = $this->importCounts())
            <x-filament::section icon="heroicon-o-clipboard-document-list" collapsible>
                <x-slot name="heading">Import log</x-slot>
                <x-slot name="description">
                    {{ number_format($c['ingested']) }} ingested ·
                    {{ number_format($c['skipped']) }} skipped (duplicates) ·
                    {{ number_format($c['failed']) }} failed — across all batches. Newest first; full library in
                    Knowledge Base → Sources.
                </x-slot>
                <div class="max-h-96 space-y-1 overflow-auto text-xs">
                    @foreach ($log as $row)
                        <div class="flex items-center gap-2.5">
                            <x-filament::badge size="sm" :color="$row['status'] === 'ingested' ? 'success' : ($row['status'] === 'failed' ? 'danger' : 'gray')">
                                {{ $row['status'] }}
                            </x-filament::badge>
                            <span class="font-mono text-[11px] text-gray-700 dark:text-gray-200">{{ $row['file'] }}</span>
                            <span class="truncate text-[11px] text-gray-500 dark:text-gray-400">{{ $row['detail'] }}</span>
                            <span class="ml-auto whitespace-nowrap text-[11px] text-gray-400">{{ $row['time']?->diffForHumans() }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>

    {{-- Guide --}}
    <x-filament::section icon="heroicon-o-book-open" collapsible collapsed>
        <x-slot name="heading">How batch import works</x-slot>
        <div class="prose prose-sm dark:prose-invert max-w-none text-sm leading-6 text-gray-600 dark:text-gray-300 space-y-3">
            <p>
                Drop a folder of PDFs (or other supported docs). Each file is queued as its own background
                job that: skips it if it’s already in the KB (by content hash or filename), pulls a short
                excerpt, asks Claude to classify it (vendor / product / subject / …), files it under
                <code>kb/raw/…</code>, and ingests it (chunks + embeddings). The batch progresses above.
            </p>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Auto-classify, review later.</strong> Imports land approved + tagged; fix anything in
                    <em>Knowledge Base → Sources</em> or with <em>Re-classify</em>.</li>
                <li><strong>New subjects.</strong> With auto-create off (default), subjects the classifier proposes
                    are listed above for one-click approval + retag; with it on, they’re created automatically.</li>
                <li><strong>Duplicates</strong> are skipped and counted, never re-imported.</li>
                <li><strong>Very large batches:</strong> mind PHP <code>upload_max_filesize</code> /
                    <code>post_max_size</code> / <code>max_file_uploads</code>; the queue worker drains files steadily.</li>
            </ul>
        </div>
    </x-filament::section>
</x-filament-panels::page>
