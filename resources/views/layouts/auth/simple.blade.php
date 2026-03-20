<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; }
            .font-display { font-family: 'Playfair Display', serif; }
            .auth-gradient {
                background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #1e40af 100%);
            }
            .auth-glow {
                background: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(59,130,246,0.3), transparent);
            }
            .feature-icon {
                background: linear-gradient(135deg, #2563eb, #7c3aed);
            }
            .glass-card {
                background: rgba(255,255,255,0.07);
                border: 1px solid rgba(255,255,255,0.12);
                backdrop-filter: blur(10px);
            }
        </style>
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-950">
        <div class="flex min-h-screen">

            {{-- ── Left branded panel (desktop only) ─────────────────────────── --}}
            <div class="relative hidden lg:flex lg:w-[52%] flex-col justify-between overflow-hidden auth-gradient p-14">

                {{-- Glow overlay --}}
                <div class="auth-glow pointer-events-none absolute inset-0"></div>

                {{-- Decorative blobs --}}
                <div class="pointer-events-none absolute -top-32 -right-32 h-96 w-96 rounded-full bg-blue-500/10 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-32 -left-32 h-96 w-96 rounded-full bg-indigo-700/20 blur-3xl"></div>

                {{-- Logo --}}
                <div class="relative z-10">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-3" wire:navigate>
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20">
                            <x-app-logo-icon class="size-6 fill-current text-white" />
                        </span>
                        <span class="text-xl font-bold tracking-tight text-white">
                            {{ config('app.name', 'The Treasurer') }}
                        </span>
                    </a>
                </div>

                {{-- Headline + features --}}
                <div class="relative z-10 space-y-10">
                    <div>
                        <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-blue-300">Church Finance Platform</p>
                        <h1 class="font-display text-4xl font-bold leading-snug text-white">
                            Everything your<br>treasurer needs,<br>in one place.
                        </h1>
                    </div>

                    <ul class="space-y-5">
                        @foreach ([
                            ['icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Track tithes, offerings, and all giving categories'],
                            ['icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'text' => 'Manage pledges, budgets, and expenses'],
                            ['icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'text' => 'Generate giving statements and financial reports'],
                            ['icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'text' => 'Multi-branch support for growing denominations'],
                        ] as $feature)
                            <li class="flex items-start gap-4">
                                <span class="feature-icon mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg">
                                    <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $feature['icon'] }}" />
                                    </svg>
                                </span>
                                <span class="text-sm leading-relaxed text-white/75">{{ $feature['text'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Bottom testimonial card --}}
                <div class="relative z-10 glass-card rounded-2xl p-5">
                    <div class="flex items-center gap-1 mb-2">
                        @for($i = 0; $i < 5; $i++)
                            <svg class="h-4 w-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                        @endfor
                    </div>
                    <p class="text-sm text-white/80 leading-relaxed">
                        "The Treasurer transformed how we manage our finances. The giving statements alone save us hours every year."
                    </p>
                    <p class="mt-3 text-xs font-semibold text-blue-300">Church Treasurer · Lusaka, Zambia</p>
                </div>
            </div>

            {{-- ── Right form panel ───────────────────────────────────────────── --}}
            <div class="flex flex-1 flex-col items-center justify-center p-6 sm:p-10 bg-white dark:bg-zinc-950">

                {{-- Mobile logo --}}
                <div class="mb-8 lg:hidden">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-3" wire:navigate>
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600">
                            <x-app-logo-icon class="size-5 fill-current text-white" />
                        </span>
                        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ config('app.name', 'The Treasurer') }}</span>
                    </a>
                </div>

                {{-- Form card --}}
                <div class="w-full max-w-sm">
                    {{ $slot }}
                </div>

                <p class="mt-10 text-center text-xs text-gray-400 dark:text-zinc-600">
                    &copy; {{ date('Y') }} {{ config('app.name', 'The Treasurer') }}. All rights reserved.
                </p>
            </div>

        </div>
        @fluxScripts
    </body>
</html>
