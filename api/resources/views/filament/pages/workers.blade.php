<x-filament-panels::page>
    @php($pending = $this->pending())
    {{-- Poll only while jobs are queued; an idle queue doesn't change. --}}
    <div @if ($pending > 0) wire:poll.5s @endif>
        @php($paused = $this->isPaused())
        @php($failed = $this->failed())
        <x-filament::section icon="heroicon-o-server-stack">
            <x-slot name="heading">Queue status</x-slot>
            <x-slot name="description">Use the buttons above to control the workers. Auto-refreshes.</x-slot>

            <div class="flex flex-wrap items-center gap-3 text-sm">
                <x-filament::badge :color="$paused ? 'warning' : 'success'" :icon="$paused ? 'heroicon-m-pause' : 'heroicon-m-bolt'">
                    {{ $paused ? 'Paused — workers idle' : 'Processing' }}
                </x-filament::badge>
                <span class="font-mono text-xs text-gray-500 dark:text-gray-400">
                    {{ number_format($pending) }} pending · {{ number_format($failed) }} failed
                </span>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section icon="heroicon-o-information-circle" collapsible collapsed>
        <x-slot name="heading">How worker control works</x-slot>
        <div class="prose prose-sm dark:prose-invert max-w-none text-sm leading-6 text-gray-600 dark:text-gray-300 space-y-2">
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Restart workers</strong> broadcasts <code>queue:restart</code>: each worker finishes its
                    current job, exits, and the supervisor (self-healing loop / systemd / supervisord) respawns it
                    with fresh code. Use after deploying code.</li>
                <li><strong>Pause / Resume</strong> toggles a flag the workers honor: while paused they stay alive
                    but stop fetching jobs (≈0 CPU). This is the safe equivalent of “stop” — nothing is killed, and a
                    job already in progress finishes. Resume to continue draining the queue.</li>
                <li><strong>Retry failed jobs</strong> requeues everything in <code>failed_jobs</code>.</li>
            </ul>
            <p class="text-xs text-gray-400">
                Pausing takes effect once workers are running this build — if you just deployed, hit
                <strong>Restart workers</strong> first.
            </p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
