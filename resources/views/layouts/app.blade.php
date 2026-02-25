<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="p-4 lg:p-6 lg:p-8">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
