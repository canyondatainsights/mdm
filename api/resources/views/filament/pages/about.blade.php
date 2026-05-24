<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400 -mt-2">
        Sidecar is a vendor-isolated knowledge platform — a Next.js chat UI over a Laravel + Filament
        backend, answering from a curated, retrieval-augmented knowledge base.
    </p>

    <div class="grid gap-6 md:grid-cols-2">
        @foreach ($groups as $group)
            <x-filament::section :icon="$group['icon']">
                <x-slot name="heading">{{ $group['heading'] }}</x-slot>
                <x-slot name="description">{{ $group['description'] }}</x-slot>

                <dl class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($group['items'] as $item)
                        <div class="flex items-start justify-between gap-4 py-2">
                            <dt class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $item[0] }}</dt>
                            <dd class="text-sm text-right font-mono text-gray-500 dark:text-gray-400">{{ $item[1] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
