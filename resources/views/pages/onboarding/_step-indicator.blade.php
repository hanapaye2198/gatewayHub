@props([
    'step' => 1,
    'totalSteps' => 4,
])

<div class="mb-6 flex flex-col items-center gap-3">
    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
        {{ __('Step :step of :total', ['step' => $step, 'total' => $totalSteps]) }}
    </p>
    <div class="flex w-full max-w-xs justify-center gap-2">
        @for ($i = 1; $i <= $totalSteps; $i++)
            <span
                class="h-1.5 flex-1 rounded-full transition-colors {{ $i <= $step ? 'bg-blue-600 dark:bg-blue-500' : 'bg-zinc-200 dark:bg-zinc-700' }}"
                aria-hidden="true"
            ></span>
        @endfor
    </div>
</div>
