@props([
    'title',
    'description',
])

<div class="flex w-full flex-col">
    <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white" style="font-family:'Inter',sans-serif">
        {{ $title }}
    </h2>
    <p class="mt-1.5 text-sm text-gray-500 dark:text-zinc-400">{{ $description }}</p>
</div>
