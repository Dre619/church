@props([
    'title'       => 'Feature not available on your plan',
    'description' => 'Upgrade your plan to unlock this feature.',
])

<div class="flex flex-col items-center justify-center py-24 px-6 text-center">
    <div class="w-16 h-16 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center mb-5">
        <x-icon name="lock-closed" class="w-8 h-8 text-amber-500" />
    </div>

    <h2 class="text-xl font-bold text-gray-900 mb-2">{{ $title }}</h2>
    <p class="text-gray-500 text-sm max-w-sm mb-6">{{ $description }}</p>

    <a href="{{ route('subscription.plans') }}"
       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-white text-sm font-semibold transition-colors shadow-sm">
        <x-icon name="arrow-up-circle" class="w-4 h-4" />
        Upgrade Plan
    </a>
</div>
