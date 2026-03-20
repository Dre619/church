@props([
    'sidebar' => false,
])

@php
    $orgLogo = auth()->user()?->myOrganization?->organization?->logo;
@endphp

@if($sidebar)
    <flux:sidebar.brand name="{{ config('app.name') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md overflow-hidden {{ $orgLogo ? '' : 'bg-accent-content text-accent-foreground' }}">
            @if($orgLogo)
                <img src="{{ Storage::url($orgLogo) }}" alt="{{ config('app.name') }}" class="size-8 object-cover">
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ config('app.name') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md overflow-hidden {{ $orgLogo ? '' : 'bg-accent-content text-accent-foreground' }}">
            @if($orgLogo)
                <img src="{{ Storage::url($orgLogo) }}" alt="{{ config('app.name') }}" class="size-8 object-cover">
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:brand>
@endif
