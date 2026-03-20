<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>The Treasurer — Church Management Platform</title>
    <meta name="description" content="The Treasurer helps churches manage members, track collections, record expenses, manage pledges, and generate beautiful reports — all in one place.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Playfair Display', serif; }

        .hero-gradient {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #1e40af 100%);
        }
        .hero-glow {
            background: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(59,130,246,0.35), transparent);
        }
        .card-hover {
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .feature-icon {
            background: linear-gradient(135deg, #2563eb, #7c3aed);
        }
        .stat-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.08), rgba(255,255,255,0.03));
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
        }
        .cta-gradient {
            background: linear-gradient(135deg, #1d4ed8, #4f46e5);
        }
        .step-line::before {
            content: '';
            position: absolute;
            left: 24px;
            top: 56px;
            bottom: -16px;
            width: 2px;
            background: linear-gradient(to bottom, #3b82f6, transparent);
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .float { animation: float 4s ease-in-out infinite; }
        .float-delay { animation: float 4s ease-in-out 2s infinite; }
    </style>
</head>
<body class="antialiased bg-white text-gray-900">

    {{-- ── Navigation ─────────────────────────────────────────────────────── --}}
    <nav class="fixed top-0 inset-x-0 z-50 bg-white/90 backdrop-blur border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                {{-- Logo --}}
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-blue-600 to-indigo-700 flex items-center justify-center shadow-md">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-gray-900">The <span class="text-blue-600">Treasurer</span></span>
                </div>

                {{-- Nav links --}}
                <div class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600">
                    <a href="#features" class="hover:text-blue-600 transition-colors">Features</a>
                    <a href="#multi-branch" class="hover:text-blue-600 transition-colors flex items-center gap-1.5">
                        Multi-Branch
                        <span class="bg-indigo-100 text-indigo-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full uppercase tracking-wide">New</span>
                    </a>
                    <a href="#how-it-works" class="hover:text-blue-600 transition-colors">How it Works</a>
                    <a href="#pricing" class="hover:text-blue-600 transition-colors">Pricing</a>
                </div>

                {{-- CTA --}}
                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-sm font-medium text-gray-700 hover:text-blue-600 transition-colors">Dashboard</a>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-blue-600 transition-colors">Sign in</a>
                        @endif
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors shadow-sm">
                                Get Started Free
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    {{-- ── Hero ─────────────────────────────────────────────────────────────── --}}
    <section class="hero-gradient relative min-h-screen flex items-center overflow-hidden pt-16">
        {{-- Glow overlay --}}
        <div class="hero-glow absolute inset-0"></div>

        {{-- Decorative circles --}}
        <div class="absolute top-1/4 right-10 w-72 h-72 bg-blue-500/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 left-10 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="grid lg:grid-cols-2 gap-16 items-center">

                {{-- Left: text --}}
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-6">
                        <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 text-blue-200 text-xs font-semibold px-3 py-1.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                            Church Finance Platform
                        </div>
                        <div class="inline-flex items-center gap-2 bg-indigo-500/20 border border-indigo-400/30 text-indigo-200 text-xs font-semibold px-3 py-1.5 rounded-full">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            Multi-Branch Support
                        </div>
                    </div>

                    <h1 class="font-display text-5xl sm:text-6xl lg:text-7xl font-bold text-white leading-tight mb-6">
                        One Platform<br>
                        Every <span class="text-blue-400">Branch</span><br>
                        Covered
                    </h1>

                    <p class="text-blue-100 text-lg leading-relaxed mb-10 max-w-lg">
                        The Treasurer brings all your church administration into one elegant platform —
                        collections, expenses, pledges, reports, and full multi-branch denomination support.
                        Manage your HQ and every branch from a single account.
                    </p>

                    <div class="flex flex-wrap items-center gap-4">
                        @auth
                            <a href="{{ route('dashboard') }}"
                               class="inline-flex items-center gap-2 bg-white text-blue-700 font-semibold px-7 py-3.5 rounded-xl hover:bg-blue-50 transition-all shadow-lg shadow-blue-900/30">
                                Go to Dashboard
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            </a>
                        @else
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}"
                                   class="inline-flex items-center gap-2 bg-white text-blue-700 font-semibold px-7 py-3.5 rounded-xl hover:bg-blue-50 transition-all shadow-lg shadow-blue-900/30">
                                    Start for Free
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                </a>
                            @endif
                            @if (Route::has('login'))
                                <a href="{{ route('login') }}"
                                   class="inline-flex items-center gap-2 border border-white/30 text-white font-medium px-7 py-3.5 rounded-xl hover:bg-white/10 transition-all">
                                    Sign In
                                </a>
                            @endif
                        @endauth
                    </div>

                    {{-- Trust badges --}}
                    <div class="flex flex-wrap items-center gap-6 mt-10 pt-8 border-t border-white/10">
                        <div class="flex items-center gap-2 text-blue-200 text-sm">
                            <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            No setup fees
                        </div>
                        <div class="flex items-center gap-2 text-blue-200 text-sm">
                            <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Multi-branch ready
                        </div>
                        <div class="flex items-center gap-2 text-blue-200 text-sm">
                            <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Multi-currency support
                        </div>
                    </div>
                </div>

                {{-- Right: dashboard mockup --}}
                <div class="hidden lg:block">
                    <div class="relative float">
                        {{-- Main card --}}
                        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                            {{-- Card header --}}
                            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center gap-3">
                                <div class="flex gap-1.5">
                                    <div class="w-3 h-3 rounded-full bg-white/30"></div>
                                    <div class="w-3 h-3 rounded-full bg-white/30"></div>
                                    <div class="w-3 h-3 rounded-full bg-white/30"></div>
                                </div>
                                <div class="flex-1 h-5 bg-white/20 rounded-full"></div>
                            </div>
                            {{-- Dashboard content --}}
                            <div class="p-6 space-y-4">
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="bg-green-50 border border-green-100 rounded-xl p-4">
                                        <p class="text-xs text-green-600 font-medium uppercase tracking-wide">Collections</p>
                                        <p class="text-2xl font-bold text-green-700 mt-1">ZMW 48,250</p>
                                        <p class="text-xs text-green-500 mt-1">↑ 12% this month</p>
                                    </div>
                                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                                        <p class="text-xs text-blue-600 font-medium uppercase tracking-wide">Members</p>
                                        <p class="text-2xl font-bold text-blue-700 mt-1">284</p>
                                        <p class="text-xs text-blue-500 mt-1">↑ 8 new this week</p>
                                    </div>
                                    <div class="bg-orange-50 border border-orange-100 rounded-xl p-4">
                                        <p class="text-xs text-orange-600 font-medium uppercase tracking-wide">Pledges</p>
                                        <p class="text-2xl font-bold text-orange-700 mt-1">ZMW 92,000</p>
                                        <p class="text-xs text-orange-500 mt-1">67% fulfilled</p>
                                    </div>
                                    <div class="bg-red-50 border border-red-100 rounded-xl p-4">
                                        <p class="text-xs text-red-600 font-medium uppercase tracking-wide">Expenses</p>
                                        <p class="text-2xl font-bold text-red-700 mt-1">ZMW 12,800</p>
                                        <p class="text-xs text-red-500 mt-1">3 categories</p>
                                    </div>
                                </div>
                                {{-- Recent payments list --}}
                                <div class="border border-gray-100 rounded-xl overflow-hidden">
                                    <div class="bg-gray-50 px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Recent Collections</div>
                                    @foreach ([['John Mwanza','Tithe','ZMW 500'],['Grace Banda','Offering','ZMW 200'],['Peter Phiri','Donation','ZMW 1,000']] as $row)
                                    <div class="flex items-center justify-between px-4 py-3 border-t border-gray-50">
                                        <div class="flex items-center gap-3">
                                            <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">{{ strtoupper(substr($row[0],0,1)) }}</div>
                                            <div>
                                                <p class="text-xs font-semibold text-gray-800">{{ $row[0] }}</p>
                                                <p class="text-xs text-gray-400">{{ $row[1] }}</p>
                                            </div>
                                        </div>
                                        <p class="text-sm font-bold text-green-600">{{ $row[2] }}</p>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Floating receipt badge --}}
                        <div class="float-delay absolute -bottom-4 -left-8 bg-white rounded-xl shadow-xl px-4 py-3 flex items-center gap-3 border border-gray-100">
                            <div class="w-9 h-9 rounded-lg bg-green-100 flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-800">PDF Receipt Generated</p>
                                <p class="text-xs text-gray-400">Receipt #00000042</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- ── Stats Strip ─────────────────────────────────────────────────────── --}}
    <section class="bg-gradient-to-r from-blue-700 to-indigo-700 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center text-white">
                <div>
                    <p class="text-4xl font-bold">100+</p>
                    <p class="text-blue-200 text-sm mt-1 font-medium">Churches Registered</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">15+</p>
                    <p class="text-blue-200 text-sm mt-1 font-medium">Currencies Supported</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">50K+</p>
                    <p class="text-blue-200 text-sm mt-1 font-medium">Payments Recorded</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">∞</p>
                    <p class="text-blue-200 text-sm mt-1 font-medium">Branches per Account</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Features ─────────────────────────────────────────────────────────── --}}
    <section id="features" class="py-24 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center mb-16">
                <span class="inline-block text-blue-600 text-sm font-semibold uppercase tracking-widest mb-3">Everything You Need</span>
                <h2 class="font-display text-4xl sm:text-5xl font-bold text-gray-900 mb-4">
                    Powerful Tools for<br>Modern Churches
                </h2>
                <p class="text-gray-500 text-lg max-w-2xl mx-auto">
                    From Sunday collections to annual reports — The Treasurer handles every financial and administrative aspect of your church.
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">

                {{-- Feature 1: Collections --}}
                <div class="bg-white rounded-2xl p-8 border border-gray-100 card-hover">
                    <div class="feature-icon w-12 h-12 rounded-xl flex items-center justify-center mb-5 shadow-md">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Collections & Payments</h3>
                    <p class="text-gray-500 leading-relaxed">Record tithes, offerings, donations, and special collections. Link payments to individual members and generate instant PDF receipts.</p>
                    <div class="flex flex-wrap gap-2 mt-5">
                        <span class="bg-blue-50 text-blue-700 text-xs font-medium px-2.5 py-1 rounded-full">Cash</span>
                        <span class="bg-blue-50 text-blue-700 text-xs font-medium px-2.5 py-1 rounded-full">Mobile Money</span>
                        <span class="bg-blue-50 text-blue-700 text-xs font-medium px-2.5 py-1 rounded-full">Bank Transfer</span>
                    </div>
                </div>

                {{-- Feature 2: PDF Receipts --}}
                <div class="bg-white rounded-2xl p-8 border border-gray-100 card-hover">
                    <div class="feature-icon w-12 h-12 rounded-xl flex items-center justify-center mb-5 shadow-md">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">PDF Receipts</h3>
                    <p class="text-gray-500 leading-relaxed">Generate beautiful, branded PDF receipts for every payment. Includes your church logo, contact details, and all transaction information.</p>
                    <div class="flex flex-wrap gap-2 mt-5">
                        <span class="bg-green-50 text-green-700 text-xs font-medium px-2.5 py-1 rounded-full">Branded</span>
                        <span class="bg-green-50 text-green-700 text-xs font-medium px-2.5 py-1 rounded-full">Printable</span>
                        <span class="bg-green-50 text-green-700 text-xs font-medium px-2.5 py-1 rounded-full">Instant</span>
                    </div>
                </div>

                {{-- Feature 3: Pledges --}}
                <div class="bg-white rounded-2xl p-8 border border-gray-100 card-hover">
                    <div class="feature-icon w-12 h-12 rounded-xl flex items-center justify-center mb-5 shadow-md">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Pledge Management</h3>
                    <p class="text-gray-500 leading-relaxed">Track member pledges against building projects, special drives, and missions. Monitor fulfillment progress with visual indicators and reminders.</p>
                    <div class="flex flex-wrap gap-2 mt-5">
                        <span class="bg-orange-50 text-orange-700 text-xs font-medium px-2.5 py-1 rounded-full">Progress Tracking</span>
                        <span class="bg-orange-50 text-orange-700 text-xs font-medium px-2.5 py-1 rounded-full">Deadlines</span>
                    </div>
                </div>

                {{-- Feature 4: Expenses --}}
                <div class="bg-white rounded-2xl p-8 border border-gray-100 card-hover">
                    <div class="feature-icon w-12 h-12 rounded-xl flex items-center justify-center mb-5 shadow-md">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Expense Tracking</h3>
                    <p class="text-gray-500 leading-relaxed">Record all church expenditures by category. Attach receipts, add descriptions, and keep a full audit trail of how your church's funds are spent.</p>
                    <div class="flex flex-wrap gap-2 mt-5">
                        <span class="bg-red-50 text-red-700 text-xs font-medium px-2.5 py-1 rounded-full">Categories</span>
                        <span class="bg-red-50 text-red-700 text-xs font-medium px-2.5 py-1 rounded-full">Receipts Upload</span>
                    </div>
                </div>

                {{-- Feature 5: Reports --}}
                <div class="bg-white rounded-2xl p-8 border border-gray-100 card-hover">
                    <div class="feature-icon w-12 h-12 rounded-xl flex items-center justify-center mb-5 shadow-md">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Reports & Excel Exports</h3>
                    <p class="text-gray-500 leading-relaxed">Generate detailed reports for collections, expenses, and pledges. Export to Excel with multiple sheets — perfect for board meetings and audits.</p>
                    <div class="flex flex-wrap gap-2 mt-5">
                        <span class="bg-purple-50 text-purple-700 text-xs font-medium px-2.5 py-1 rounded-full">Excel Export</span>
                        <span class="bg-purple-50 text-purple-700 text-xs font-medium px-2.5 py-1 rounded-full">Member Breakdown</span>
                    </div>
                </div>

                {{-- Feature 6: Multi-Branch --}}
                <div class="bg-white rounded-2xl p-8 border border-indigo-100 card-hover relative overflow-hidden">
                    <div class="absolute top-3 right-3 bg-indigo-600 text-white text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full">New</div>
                    <div class="feature-icon w-12 h-12 rounded-xl flex items-center justify-center mb-5 shadow-md">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Multi-Branch Management</h3>
                    <p class="text-gray-500 leading-relaxed">Run a denomination with multiple branches? Each branch gets its own isolated finances, members, and reports — all manageable from one login with a simple branch switcher.</p>
                    <div class="flex flex-wrap gap-2 mt-5">
                        <span class="bg-indigo-50 text-indigo-700 text-xs font-medium px-2.5 py-1 rounded-full">Headquarters</span>
                        <span class="bg-indigo-50 text-indigo-700 text-xs font-medium px-2.5 py-1 rounded-full">Branch Switcher</span>
                        <span class="bg-indigo-50 text-indigo-700 text-xs font-medium px-2.5 py-1 rounded-full">Independent Financials</span>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- ── Multi-Branch Showcase ───────────────────────────────────────────── --}}
    <section id="multi-branch" class="py-24 hero-gradient relative overflow-hidden">
        <div class="hero-glow absolute inset-0"></div>
        <div class="absolute top-0 right-0 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-72 h-72 bg-indigo-700/20 rounded-full blur-3xl"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-16 items-center">

                {{-- Left: text --}}
                <div>
                    <span class="inline-flex items-center gap-2 bg-indigo-500/20 border border-indigo-400/30 text-indigo-200 text-xs font-semibold px-3 py-1.5 rounded-full mb-6">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        Multi-Branch Support
                    </span>
                    <h2 class="font-display text-4xl sm:text-5xl font-bold text-white mb-6 leading-tight">
                        One Denomination,<br>Every Branch<br><span class="text-blue-400">Under Control</span>
                    </h2>
                    <p class="text-blue-100 text-lg leading-relaxed mb-10">
                        Growing denominations shouldn't need separate logins for every branch. The Treasurer lets you
                        create branches under your headquarters, each with their own members, collections,
                        expenses, and reports — all accessible from a single account with one click.
                    </p>

                    <ul class="space-y-5">
                        @foreach ([
                            ['Isolated finances per branch — no data mixing between locations'],
                            ['Instant branch switcher in the sidebar — switch with one click'],
                            ['Each branch gets its own subscription and feature set'],
                            ['Shared admin access — one user can manage all branches'],
                        ] as $item)
                            <li class="flex items-start gap-3">
                                <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-500/30 ring-1 ring-blue-400/50">
                                    <svg class="h-3.5 w-3.5 text-blue-300" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <span class="text-blue-100 text-sm leading-relaxed">{{ $item[0] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Right: branch tree mockup --}}
                <div class="flex flex-col gap-4">

                    {{-- HQ card --}}
                    <div class="stat-card rounded-2xl p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center font-bold text-white text-sm">G</div>
                                <div>
                                    <p class="font-semibold text-white text-sm">Grace Community Church</p>
                                    <p class="text-blue-300 text-xs">Headquarters · Lusaka</p>
                                </div>
                            </div>
                            <span class="bg-green-500/20 text-green-300 text-xs font-semibold px-2.5 py-1 rounded-full border border-green-500/30">Active</span>
                        </div>
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="bg-white/5 rounded-xl py-3">
                                <p class="text-white font-bold text-lg">284</p>
                                <p class="text-blue-300 text-xs mt-0.5">Members</p>
                            </div>
                            <div class="bg-white/5 rounded-xl py-3">
                                <p class="text-white font-bold text-lg">ZMW 48K</p>
                                <p class="text-blue-300 text-xs mt-0.5">Collected</p>
                            </div>
                            <div class="bg-white/5 rounded-xl py-3">
                                <p class="text-white font-bold text-lg">3</p>
                                <p class="text-blue-300 text-xs mt-0.5">Branches</p>
                            </div>
                        </div>
                    </div>

                    {{-- Branch list --}}
                    <div class="pl-6 space-y-3 relative">
                        {{-- Vertical connector line --}}
                        <div class="absolute left-2 top-2 bottom-2 w-0.5 bg-gradient-to-b from-blue-500/60 to-transparent"></div>

                        @foreach ([
                            ['L','Lusaka CBD Branch','ZMW 32,100','142 members','bg-purple-600'],
                            ['N','Ndola Branch','ZMW 28,750','98 members','bg-indigo-600'],
                            ['K','Kitwe Branch','ZMW 19,400','67 members','bg-blue-600'],
                        ] as $branch)
                            <div class="relative flex items-center gap-3 stat-card rounded-xl px-4 py-3">
                                {{-- Branch connector dot --}}
                                <div class="absolute -left-4 top-1/2 -translate-y-1/2 w-2 h-2 rounded-full bg-blue-400 ring-2 ring-blue-500/30"></div>
                                <div class="w-8 h-8 rounded-lg {{ $branch[4] }} flex items-center justify-center font-bold text-white text-xs flex-shrink-0">{{ $branch[0] }}</div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-white text-xs truncate">{{ $branch[1] }}</p>
                                    <p class="text-blue-300 text-xs">{{ $branch[3] }}</p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-green-300 font-semibold text-xs">{{ $branch[2] }}</p>
                                    <p class="text-blue-400 text-xs">this month</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Branch switcher hint --}}
                    <div class="stat-card rounded-xl px-4 py-3 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        </div>
                        <p class="text-blue-200 text-xs">Switch between branches instantly from the sidebar — no separate logins needed.</p>
                    </div>

                </div>
            </div>
        </div>
    </section>

    {{-- ── How It Works ─────────────────────────────────────────────────────── --}}
    <section id="how-it-works" class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="inline-block text-blue-600 text-sm font-semibold uppercase tracking-widest mb-3">Simple Process</span>
                <h2 class="font-display text-4xl sm:text-5xl font-bold text-gray-900 mb-4">Up and Running in Minutes</h2>
                <p class="text-gray-500 text-lg max-w-2xl mx-auto">
                    No complicated setup. No IT department needed. Your church can be fully operational on The Treasurer in just a few steps.
                </p>
            </div>

            <div class="max-w-3xl mx-auto">
                @foreach ([
                    ['01', 'Create Your Account', 'Register with your email and set up your church profile — name, logo, address, and preferred currency.', 'bg-blue-100 text-blue-700'],
                    ['02', 'Set Up Categories', 'Define your payment categories (Tithe, Offering, Donations) and expense categories to organize your finances.', 'bg-indigo-100 text-indigo-700'],
                    ['03', 'Add Your Members', 'Invite your church members and assign roles. Members can be linked to payments and pledges automatically.', 'bg-purple-100 text-purple-700'],
                    ['04', 'Record & Report', 'Start recording collections and expenses. Generate reports and PDF receipts whenever you need them.', 'bg-green-100 text-green-700'],
                ] as $step)
                <div class="relative flex gap-6 pb-10 {{ $loop->last ? '' : 'step-line' }}">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 rounded-full {{ $step[3] }} flex items-center justify-center font-bold text-lg">
                            {{ $step[0] }}
                        </div>
                    </div>
                    <div class="pt-2 pb-2">
                        <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $step[1] }}</h3>
                        <p class="text-gray-500 leading-relaxed">{{ $step[2] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Currency Section ─────────────────────────────────────────────────── --}}
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div>
                    <span class="inline-block text-blue-600 text-sm font-semibold uppercase tracking-widest mb-3">Multi-Currency</span>
                    <h2 class="font-display text-4xl font-bold text-gray-900 mb-5">
                        Your Church, Your Currency
                    </h2>
                    <p class="text-gray-500 text-lg leading-relaxed mb-8">
                        The Treasurer supports over 15 African and international currencies. Switch your organization's default currency at any time, and all amounts, receipts, and reports will automatically reflect the correct symbol.
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        @foreach ([['ZMW','Zambian Kwacha','ZMW'],['NGN','Nigerian Naira','₦'],['KES','Kenyan Shilling','KSh'],['GHS','Ghanaian Cedi','₵'],['USD','US Dollar','$'],['ZAR','South African Rand','R']] as $c)
                        <div class="flex items-center gap-3 bg-white border border-gray-100 rounded-xl px-4 py-3">
                            <div class="w-9 h-9 rounded-full bg-blue-50 flex items-center justify-center font-bold text-blue-700 text-sm">{{ $c[2] }}</div>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm">{{ $c[1] }}</p>
                                <p class="text-xs text-gray-400">{{ $c[0] }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="space-y-4">
                    {{-- Receipt preview card --}}
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-700 to-indigo-700 px-6 py-5 flex items-center justify-between">
                            <div>
                                <p class="text-white font-bold text-lg">Grace Community Church</p>
                                <p class="text-blue-200 text-xs mt-0.5">Lusaka, Zambia · +260 97 123 4567</p>
                            </div>
                            <div class="text-right">
                                <p class="text-blue-300 text-xs font-semibold uppercase tracking-wider">Receipt</p>
                                <p class="text-white font-bold text-xl">#00000042</p>
                            </div>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center gap-1.5 bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                    Payment Confirmed
                                </span>
                                <span class="text-gray-400 text-sm">Issued: March 10, 2026</span>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase font-semibold tracking-wider">Received From</p>
                                <p class="text-gray-900 font-semibold mt-1">John Mwanza</p>
                            </div>
                            <div class="bg-blue-50 border border-blue-100 rounded-xl px-5 py-4 flex items-center justify-between">
                                <span class="text-blue-700 font-semibold text-sm">Amount Received</span>
                                <span class="text-blue-700 font-extrabold text-2xl">ZMW 500.00</span>
                            </div>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-400 text-xs uppercase font-semibold tracking-wider">Category</p>
                                    <p class="text-gray-800 font-medium mt-1">Tithe</p>
                                </div>
                                <div>
                                    <p class="text-gray-400 text-xs uppercase font-semibold tracking-wider">Method</p>
                                    <p class="text-gray-800 font-medium mt-1">Mobile Money</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Pricing ──────────────────────────────────────────────────────────── --}}
    <section id="pricing" class="py-24 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center mb-16">
                <span class="inline-block text-blue-600 text-sm font-semibold uppercase tracking-widest mb-3">Transparent Pricing</span>
                <h2 class="font-display text-4xl sm:text-5xl font-bold text-gray-900 mb-4">
                    Simple Plans for Every Church
                </h2>
                <p class="text-gray-500 text-lg max-w-2xl mx-auto">
                    Choose a plan that fits your congregation. All plans include full access to collections, expenses, pledges, reports, and PDF receipts.
                </p>
                {{-- Duration discount badges --}}
                <div class="flex flex-wrap justify-center gap-3 mt-6">
                    <span class="inline-flex items-center gap-1.5 bg-green-50 border border-green-200 text-green-700 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                        6 months — save 10%
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-blue-50 border border-blue-200 text-blue-700 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                        12 months — save 20%
                    </span>
                </div>
            </div>

            @if($plans->isNotEmpty())
                {{-- Dynamic plans from DB --}}
                @php
                    $highlightIndex = $plans->count() > 1 ? (int) floor($plans->count() / 2) : 0;
                    $allFeatures = [
                        'Unlimited Collections & Payments',
                        'PDF Receipt Generation',
                        'Expense Tracking',
                        'Pledge Management',
                        'Member Management',
                        'Collections Report & Excel Export',
                        'Expenses Report & Excel Export',
                        'Pledges Report & Excel Export',
                        'Multi-currency Support',
                        'Multi-Branch Support',
                        'Church Profile & Logo',
                    ];
                @endphp
                <div class="grid md:grid-cols-{{ min($plans->count(), 3) }} gap-8 max-w-5xl mx-auto">
                    @foreach($plans as $index => $plan)
                        @php
                            $isPopular  = $index === $highlightIndex;
                            $maxFeatures = ($plan->max_members && $plan->max_members <= 100)  ? 5 :
                                          ($plan->max_members && $plan->max_members <= 500 ? 8 : count($allFeatures));
                            $planFeatures = array_slice($allFeatures, 0, $maxFeatures);
                        @endphp
                        <div class="relative flex flex-col rounded-2xl border {{ $isPopular ? 'border-blue-500 shadow-xl shadow-blue-100 scale-105' : 'border-gray-200 bg-white shadow-sm' }} overflow-hidden card-hover">

                            @if($isPopular)
                                <div class="absolute top-0 inset-x-0 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xs font-bold text-center py-1.5 uppercase tracking-widest">
                                    Most Popular
                                </div>
                            @endif

                            <div class="p-8 {{ $isPopular ? 'pt-10 bg-white' : 'bg-white' }}">

                                {{-- Plan name --}}
                                <h3 class="text-xl font-bold text-gray-900 mb-1">{{ $plan->name }}</h3>
                                <p class="text-sm text-gray-400 mb-6">
                                    @if($plan->max_members)
                                        Up to <strong class="text-gray-600">{{ number_format($plan->max_members) }}</strong> members
                                    @else
                                        <strong class="text-gray-600">Unlimited</strong> members
                                    @endif
                                    @if($plan->is_trial)
                                        · <strong class="text-gray-600">{{ $plan->trial_days }}-day</strong> free trial
                                    @endif
                                </p>

                                {{-- Price --}}
                                <div class="mb-8">
                                    @if($plan->price == 0)
                                        <div class="flex items-end gap-1">
                                            <span class="text-5xl font-extrabold text-gray-900">Free</span>
                                        </div>
                                        <p class="text-sm text-gray-400 mt-1">No credit card required</p>
                                    @elseif($plan->hasActiveDiscount())
                                        <div class="flex items-end gap-2 flex-wrap">
                                            <span class="text-2xl font-bold text-gray-400 mb-2">ZMW</span>
                                            <span class="text-5xl font-extrabold text-emerald-600">{{ number_format($plan->discountedPrice(), 0) }}</span>
                                            <span class="text-xl font-semibold text-gray-400 line-through mb-1">{{ number_format($plan->price, 0) }}</span>
                                            <span class="text-gray-400 mb-2">/mo</span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-2 flex-wrap">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold">
                                                🏷 Save {{ $plan->discount_percentage }}% — early adoption
                                            </span>
                                            @if($plan->discount_max_organizations)
                                                @php $spotsLeft = $plan->discount_max_organizations - $plan->organizationPlans()->count(); @endphp
                                                <span class="text-xs text-gray-400">{{ $spotsLeft }} spot{{ $spotsLeft === 1 ? '' : 's' }} left</span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="flex items-end gap-1">
                                            <span class="text-2xl font-bold text-gray-500 mb-2">ZMW</span>
                                            <span class="text-5xl font-extrabold {{ $isPopular ? 'text-blue-600' : 'text-gray-900' }}">{{ number_format($plan->price, 0) }}</span>
                                            <span class="text-gray-400 mb-2">/mo</span>
                                        </div>
                                        <p class="text-sm text-gray-400 mt-1">
                                            Billed monthly · save up to 20% annually
                                        </p>
                                    @endif
                                </div>

                                {{-- CTA button --}}
                                @auth
                                    <a href="{{ Route::has('subscription.plans') ? route('subscription.plans') : route('dashboard') }}"
                                       class="block w-full text-center font-semibold py-3 px-6 rounded-xl transition-all
                                           {{ $isPopular
                                               ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 shadow-md shadow-blue-200'
                                               : 'bg-gray-100 text-gray-800 hover:bg-gray-200' }}">
                                        Manage Subscription
                                    </a>
                                @else
                                    @if(Route::has('register'))
                                        <a href="{{ route('register') }}"
                                           class="block w-full text-center font-semibold py-3 px-6 rounded-xl transition-all
                                               {{ $isPopular
                                                   ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 shadow-md shadow-blue-200'
                                                   : 'bg-gray-100 text-gray-800 hover:bg-gray-200' }}">
                                            Get Started
                                        </a>
                                    @endif
                                @endauth
                            </div>

                            {{-- Features --}}
                            <div class="px-8 pb-8 flex-1 bg-white">
                                <div class="border-t border-gray-100 pt-6 space-y-3">
                                    @foreach($planFeatures as $feature)
                                        <div class="flex items-center gap-3">
                                            <div class="flex-shrink-0 w-5 h-5 rounded-full {{ $isPopular ? 'bg-blue-100' : 'bg-green-100' }} flex items-center justify-center">
                                                <svg class="w-3 h-3 {{ $isPopular ? 'text-blue-600' : 'text-green-600' }}" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                            <span class="text-sm text-gray-600">{{ $feature }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- FAQ-style note --}}
                <p class="text-center text-sm text-gray-400 mt-10">
                    All prices are in Zambian Kwacha (ZMW). Discounts apply when subscribing for 6 or 12 months at checkout.
                    <a href="{{ Route::has('login') ? route('login') : '#' }}" class="text-blue-600 hover:underline ml-1">Sign in to subscribe →</a>
                </p>

            @else
                {{-- Fallback if no plans in DB yet --}}
                <div class="text-center py-16 bg-white rounded-2xl border border-gray-100 shadow-sm max-w-lg mx-auto">
                    <div class="w-16 h-16 rounded-full bg-blue-50 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Plans Coming Soon</h3>
                    <p class="text-gray-500 text-sm">Pricing plans are being set up. Register now to get early access.</p>
                    @if(Route::has('register'))
                        <a href="{{ route('register') }}" class="inline-block mt-5 bg-blue-600 text-white font-semibold px-6 py-2.5 rounded-lg hover:bg-blue-700 transition-colors text-sm">
                            Register Free
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>

    {{-- ── CTA ──────────────────────────────────────────────────────────────── --}}
    <section class="py-24 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="cta-gradient rounded-3xl px-8 py-16 shadow-2xl relative overflow-hidden">
                {{-- Decorative --}}
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full translate-y-1/2 -translate-x-1/2"></div>

                <div class="relative">
                    <span class="inline-block bg-white/20 border border-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-full mb-6">
                        Get Started Today
                    </span>
                    <h2 class="font-display text-4xl sm:text-5xl font-bold text-white mb-5">
                        Ready to Transform Your<br>Church Administration?
                    </h2>
                    <p class="text-blue-100 text-lg mb-10 max-w-xl mx-auto">
                        Join churches that trust The Treasurer to manage their finances with transparency and accountability.
                    </p>
                    <div class="flex flex-wrap justify-center gap-4">
                        @auth
                            <a href="{{ route('dashboard') }}"
                               class="inline-flex items-center gap-2 bg-white text-blue-700 font-semibold px-8 py-4 rounded-xl hover:bg-blue-50 transition-all shadow-lg text-lg">
                                Open Dashboard
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            </a>
                        @else
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}"
                                   class="inline-flex items-center gap-2 bg-white text-blue-700 font-semibold px-8 py-4 rounded-xl hover:bg-blue-50 transition-all shadow-lg text-lg">
                                    Create Free Account
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                </a>
                            @endif
                            @if (Route::has('login'))
                                <a href="{{ route('login') }}"
                                   class="inline-flex items-center gap-2 border-2 border-white/50 text-white font-semibold px-8 py-4 rounded-xl hover:bg-white/10 transition-all text-lg">
                                    Sign In
                                </a>
                            @endif
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Footer ───────────────────────────────────────────────────────────── --}}
    <footer class="bg-gray-900 text-gray-400 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-600 to-indigo-700 flex items-center justify-center shadow">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    <span class="text-white font-bold">The <span class="text-blue-400">Treasurer</span></span>
                </div>
                <p class="text-sm text-center">
                    Built for churches across Africa. Trusted for transparency.
                </p>
                <p class="text-sm">&copy; {{ date('Y') }} The Treasurer. All rights reserved.</p>
            </div>
        </div>
    </footer>

    {{-- Smooth scroll --}}
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                document.querySelector(a.getAttribute('href'))?.scrollIntoView({ behavior: 'smooth' });
            });
        });
    </script>
</body>
</html>
