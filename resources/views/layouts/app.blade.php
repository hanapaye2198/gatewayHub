<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="app-shell-main">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
