<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- SEO Primary Meta Tags -->
    <title>{{ $settings['site_title'] ?? 'Lindr — Dating & Social Connection Platform' }}</title>
    <meta name="description" content="{{ $settings['meta_description'] ?? 'Lindr is a modern social dating platform featuring realtime matching, coin-powered messaging, virtual gifts, and instant creator withdrawals.' }}">
    <meta name="keywords" content="dating app, social chat, instant matching, virtual gifts, m-pesa dating, kenya dating, lindr app">
    <meta name="robots" content="index, follow">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ config('app.url') }}">
    <meta property="og:title" content="{{ $settings['site_title'] ?? 'Lindr — Dating & Social Connection Platform' }}">
    <meta property="og:description" content="{{ $settings['meta_description'] ?? 'Lindr is a modern social dating platform featuring realtime matching, coin-powered messaging, virtual gifts, and instant creator withdrawals.' }}">
    <meta property="og:image" content="{{ $media['hero_lifestyle'] ?? asset('storage/landing/hero_lifestyle.png') }}">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ config('app.url') }}">
    <meta name="twitter:title" content="{{ $settings['site_title'] ?? 'Lindr — Dating & Social Connection Platform' }}">
    <meta name="twitter:description" content="{{ $settings['meta_description'] ?? 'Lindr is a modern social dating platform featuring realtime matching, coin-powered messaging, virtual gifts, and instant creator withdrawals.' }}">
    <meta name="twitter:image" content="{{ $media['hero_lifestyle'] ?? asset('storage/landing/hero_lifestyle.png') }}">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Schema.org Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "SoftwareApplication",
      "name": "Lindr",
      "operatingSystem": "iOS, Android",
      "applicationCategory": "SocialNetworkingApplication",
      "offers": {
        "@@type": "Offer",
        "price": "0",
        "priceCurrency": "USD"
      },
      "description": "Dating and social connection platform with realtime chat, virtual gifts, and multi-gateway digital payments.",
      "publisher": {
        "@@type": "Organization",
        "name": "Lindr Inc.",
        "url": "{{ config('app.url') }}"
      }
    }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-[#0B0F19] text-slate-100 font-sans antialiased selection:bg-rose-500 selection:text-white relative overflow-x-hidden">

    <!-- Background Decorative Glows -->
    <div class="pointer-events-none fixed -top-40 -left-40 w-[500px] h-[500px] bg-rose-600/10 rounded-full blur-[120px]"></div>
    <div class="pointer-events-none fixed top-1/3 -right-40 w-[600px] h-[600px] bg-amber-500/10 rounded-full blur-[140px]"></div>
    <div class="pointer-events-none fixed -bottom-40 left-1/4 w-[500px] h-[500px] bg-purple-600/10 rounded-full blur-[130px]"></div>

    <!-- 1. Header Navigation Bar -->
    <header class="sticky top-0 z-50 bg-[#0B0F19]/80 backdrop-blur-xl border-b border-slate-800/60 transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="/" class="flex items-center gap-3 group">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-rose-500 to-amber-400 p-0.5 shadow-lg shadow-rose-500/20 group-hover:scale-105 transition-transform">
                    <div class="w-full h-full bg-[#0B0F19] rounded-[10px] flex items-center justify-center">
                        <svg class="w-5 h-5 text-rose-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-black tracking-tight text-white group-hover:text-rose-400 transition-colors">Lindr</span>
            </a>

            <!-- Navigation Links -->
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-300">
                <a href="#features" class="hover:text-rose-400 transition-colors">Features</a>
                <a href="#how-it-works" class="hover:text-rose-400 transition-colors">How It Works</a>
                <a href="#coins" class="hover:text-rose-400 transition-colors">Coins & Pricing</a>
                <a href="#earnings" class="hover:text-rose-400 transition-colors">Earnings</a>
                <a href="#safety" class="hover:text-rose-400 transition-colors">Safety</a>
                <a href="#faq" class="hover:text-rose-400 transition-colors">FAQ</a>
            </nav>

            <!-- Actions & Admin Link -->
            <div class="flex items-center gap-4">
                <a href="/access" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-700/80 bg-slate-900/60 text-xs font-semibold text-slate-300 hover:text-white hover:border-slate-600 transition-colors" title="Admin Portal">
                    <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Admin Access
                </a>

                @if(!empty($settings['app_store_url']) || !empty($settings['google_play_url']))
                    <a href="#download" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-rose-500 to-amber-500 hover:from-rose-600 hover:to-amber-600 text-white font-semibold text-xs shadow-lg shadow-rose-500/25 hover:shadow-rose-500/40 transition-all">
                        <span>Get the App</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </a>
                @endif
            </div>
        </div>
    </header>

    <!-- 2. Hero Section -->
    <section class="relative pt-12 pb-24 lg:pt-20 lg:pb-32 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                <!-- Left Text Column -->
                <div class="lg:col-span-7 space-y-8 text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold tracking-wide">
                        <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                        {{ $settings['hero_badge_text'] ?? 'The Next Generation Social Dating Platform' }}
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-white leading-[1.1]">
                        Connect Authentically, <br class="hidden sm:inline"/>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-rose-400 via-amber-300 to-amber-500">Express Generously</span>
                    </h1>

                    <p class="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto lg:mx-0 font-normal leading-relaxed">
                        {{ $settings['hero_subtitle'] ?? 'Lindr combines real-time profile discovery, tokenized chat messages, instant virtual gifting, and automated creator earnings into one seamless mobile experience.' }}
                    </p>

                    <!-- Download Buttons & CTA -->
                    <div class="flex flex-wrap items-center justify-center lg:justify-start gap-4 pt-2">
                        @if(!empty($settings['app_store_url']))
                            <a href="{{ $settings['app_store_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-3 px-6 py-3.5 rounded-xl bg-slate-900 border border-slate-700/80 hover:border-rose-500/50 hover:bg-slate-800 text-white font-medium text-sm transition-all shadow-xl">
                                <svg class="w-6 h-6 fill-current text-white" viewBox="0 0 24 24">
                                    <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.32c.67-.82 1.13-1.96.99-3.12-1 .04-2.19.67-2.88 1.48-.61.71-1.15 1.87-.99 3.01 1.11.09 2.22-.55 2.88-1.37z"/>
                                </svg>
                                <div class="text-left leading-tight">
                                    <div class="text-[10px] text-slate-400 uppercase font-semibold">Download on</div>
                                    <div class="text-sm font-bold">App Store</div>
                                </div>
                            </a>
                        @endif

                        @if(!empty($settings['google_play_url']))
                            <a href="{{ $settings['google_play_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-3 px-6 py-3.5 rounded-xl bg-slate-900 border border-slate-700/80 hover:border-amber-500/50 hover:bg-slate-800 text-white font-medium text-sm transition-all shadow-xl">
                                <svg class="w-6 h-6 fill-current text-white" viewBox="0 0 24 24">
                                    <path d="M3 20.5v-17c0-.55.45-1 1-1h.5c.2 0 .38.07.53.2l12 10.5-12 10.5c-.15.13-.33.2-.53.2H4c-.55 0-1-.45-1-1zm14.8-7.8l3-2.6c.4-.35.4-.95 0-1.3l-3-2.6-3.2 2.8 3.2 3.7z"/>
                                </svg>
                                <div class="text-left leading-tight">
                                    <div class="text-[10px] text-slate-400 uppercase font-semibold">Get it on</div>
                                    <div class="text-sm font-bold">Google Play</div>
                                </div>
                            </a>
                        @endif

                        <a href="#features" class="inline-flex items-center gap-2 px-6 py-3.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 text-slate-200 font-medium text-sm transition-all">
                            <span>Explore Features</span>
                        </a>
                    </div>
                </div>

                <!-- Right Visual Column (Dual Images with Floating Badges) -->
                <div class="lg:col-span-5 relative flex justify-center">
                    <div class="relative w-full max-w-sm sm:max-w-md">
                        <!-- Main Lifestyle Background Card -->
                        <div class="rounded-3xl overflow-hidden border border-slate-800 shadow-2xl shadow-rose-950/40 relative group">
                            <img src="{{ $media['hero_lifestyle'] ?? asset('storage/landing/hero_lifestyle.png') }}" alt="Lindr Social Dating Lifestyle" class="w-full h-[420px] object-cover filter contrast-[1.05]">
                            <div class="absolute inset-0 bg-gradient-to-t from-[#0B0F19] via-transparent to-transparent"></div>
                        </div>

                        <!-- Phone Mockup Overlay -->
                        <div class="absolute -bottom-8 -right-4 sm:-right-8 w-48 sm:w-56 rounded-2xl overflow-hidden border-2 border-slate-700/80 shadow-2xl bg-slate-900 transform hover:scale-105 transition-transform duration-300">
                            <img src="{{ $media['hero_phone_mockup'] ?? asset('storage/landing/hero_phone_mockup.png') }}" alt="Lindr Mobile App Mockup" class="w-full h-auto">
                        </div>

                        <!-- Floating Glassmorphism Badge 1 -->
                        <div class="absolute -top-6 -left-6 bg-slate-900/90 backdrop-blur-xl border border-rose-500/30 px-4 py-3 rounded-2xl shadow-xl flex items-center gap-3 animate-bounce" style="animation-duration: 4s;">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-r from-rose-500 to-amber-400 flex items-center justify-center text-white text-xs font-bold">
                                ♥
                            </div>
                            <div>
                                <div class="text-xs font-bold text-white">Instant Matches</div>
                                <div class="text-[10px] text-slate-400">100% Real Verification</div>
                            </div>
                        </div>

                        <!-- Floating Glassmorphism Badge 2 -->
                        <div class="absolute bottom-12 -left-8 bg-slate-900/90 backdrop-blur-xl border border-amber-500/30 px-4 py-3 rounded-2xl shadow-xl flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-amber-500/20 text-amber-400 flex items-center justify-center text-xs">
                                ⚡
                            </div>
                            <div>
                                <div class="text-xs font-bold text-white">Instant Payouts</div>
                                <div class="text-[10px] text-slate-400">M-Pesa & Bank Withdrawal</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Core Platform Principles / 3 Pillars -->
    <section id="features" class="py-20 bg-slate-900/40 border-y border-slate-800/60 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-widest text-rose-400">Core Experience</h2>
                <h3 class="text-3xl sm:text-4xl font-extrabold text-white">Built for Genuine Connection & Mutual Respect</h3>
                <p class="text-slate-400 text-sm sm:text-base">Every feature in Lindr is architected to foster authentic interaction, secure transactions, and transparent creator monetization.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Pillar 1 -->
                <div class="p-8 rounded-2xl bg-[#111827] border border-slate-800 hover:border-rose-500/40 transition-all duration-300 group">
                    <div class="w-12 h-12 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <h4 class="text-xl font-bold text-white mb-3">Authentic Discovery</h4>
                    <p class="text-slate-400 text-sm leading-relaxed">Discover real profiles filtered by location, interests, and activity. Our matching algorithms connect you with people looking for genuine interaction.</p>
                </div>

                <!-- Pillar 2 -->
                <div class="p-8 rounded-2xl bg-[#111827] border border-slate-800 hover:border-amber-500/40 transition-all duration-300 group">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h4 class="text-xl font-bold text-white mb-3">Coin-Powered Chat</h4>
                    <p class="text-slate-400 text-sm leading-relaxed">Send single paid messages or unlock unlimited conversation windows using internal Lindr Coins. Quality interactions over spam.</p>
                </div>

                <!-- Pillar 3 -->
                <div class="p-8 rounded-2xl bg-[#111827] border border-slate-800 hover:border-purple-500/40 transition-all duration-300 group">
                    <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <h4 class="text-xl font-bold text-white mb-3">Empowering Creator Earnings</h4>
                    <p class="text-slate-400 text-sm leading-relaxed">Female users earn real coins for active chatting and virtual gift receipts, convertible straight to M-Pesa or local bank accounts.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. App Interface Showcase (4 Mockup Showcase) -->
    <section class="py-24 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-widest text-amber-400">Designed for Mobile</h2>
                <h3 class="text-3xl sm:text-4xl font-extrabold text-white">Sleek, Fast & Intuitive Mobile Interface</h3>
                <p class="text-slate-400 text-sm sm:text-base">Experience fluid 60fps mobile interaction with instant notifications and effortless navigation.</p>
            </div>

            <!-- Interface Grid -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Card 1: Discover -->
                <div class="bg-[#111827] rounded-2xl border border-slate-800 p-4 flex flex-col items-center hover:border-rose-500/40 transition-colors">
                    <div class="w-full rounded-xl overflow-hidden mb-4 bg-slate-900 border border-slate-800">
                        <img src="{{ $media['discover_screen'] ?? asset('storage/landing/discover_screen.png') }}" alt="Discover Screen" class="w-full h-64 object-cover">
                    </div>
                    <h4 class="text-base font-bold text-white mb-1">Discovery Feed</h4>
                    <p class="text-xs text-slate-400 text-center">Swipe through verified nearby profiles with detailed bios and photos.</p>
                </div>

                <!-- Card 2: Match -->
                <div class="bg-[#111827] rounded-2xl border border-slate-800 p-4 flex flex-col items-center hover:border-rose-500/40 transition-colors">
                    <div class="w-full rounded-xl overflow-hidden mb-4 bg-slate-900 border border-slate-800">
                        <img src="{{ $media['match_screen'] ?? asset('storage/landing/match_screen.png') }}" alt="Match Screen" class="w-full h-64 object-cover">
                    </div>
                    <h4 class="text-base font-bold text-white mb-1">Instant Matches</h4>
                    <p class="text-xs text-slate-400 text-center">Instant double-opt-in matching celebration and direct message trigger.</p>
                </div>

                <!-- Card 3: Chat -->
                <div class="bg-[#111827] rounded-2xl border border-slate-800 p-4 flex flex-col items-center hover:border-rose-500/40 transition-colors">
                    <div class="w-full rounded-xl overflow-hidden mb-4 bg-slate-900 border border-slate-800">
                        <img src="{{ $media['chat_screen'] ?? asset('storage/landing/chat_screen.png') }}" alt="Chat Screen" class="w-full h-64 object-cover">
                    </div>
                    <h4 class="text-base font-bold text-white mb-1">Realtime Chat</h4>
                    <p class="text-xs text-slate-400 text-center">1-on-1 private messaging with integrated virtual gift sender.</p>
                </div>

                <!-- Card 4: Wallet -->
                <div class="bg-[#111827] rounded-2xl border border-slate-800 p-4 flex flex-col items-center hover:border-rose-500/40 transition-colors">
                    <div class="w-full rounded-xl overflow-hidden mb-4 bg-slate-900 border border-slate-800">
                        <img src="{{ $media['wallet_screen'] ?? asset('storage/landing/wallet_screen.png') }}" alt="Wallet Screen" class="w-full h-64 object-cover">
                    </div>
                    <h4 class="text-base font-bold text-white mb-1">Coin Wallet</h4>
                    <p class="text-xs text-slate-400 text-center">Real-time balance, package top-up, and withdrawal status tracker.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 5. How Matching Works -->
    <section id="how-it-works" class="py-20 bg-slate-900/40 border-y border-slate-800/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-widest text-rose-400">Step-By-Step</h2>
                <h3 class="text-3xl sm:text-4xl font-extrabold text-white">How Lindr Works in 3 Simple Steps</h3>
            </div>

            <div class="grid md:grid-cols-3 gap-8 relative">
                <!-- Step 1 -->
                <div class="relative p-8 rounded-2xl bg-[#111827] border border-slate-800">
                    <div class="text-4xl font-black text-rose-500/30 mb-4">01</div>
                    <h4 class="text-lg font-bold text-white mb-2">Create & Discover</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">Sign up in seconds, upload your best photos, set your preferences, and start exploring genuine local profiles.</p>
                </div>

                <!-- Step 2 -->
                <div class="relative p-8 rounded-2xl bg-[#111827] border border-slate-800">
                    <div class="text-4xl font-black text-amber-500/30 mb-4">02</div>
                    <h4 class="text-lg font-bold text-white mb-2">Like & Match</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">Express interest with a simple swipe or like. When mutual interest occurs, a match is instantly created.</p>
                </div>

                <!-- Step 3 -->
                <div class="relative p-8 rounded-2xl bg-[#111827] border border-slate-800">
                    <div class="text-4xl font-black text-purple-500/30 mb-4">03</div>
                    <h4 class="text-lg font-bold text-white mb-2">Chat & Send Gifts</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">Initiate conversation using coins, send virtual gifts to delight your match, and build genuine social connections.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. Paid Chat & Coin Packages Teaser -->
    <section id="coins" class="py-24 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-widest text-amber-400">Transparent Token Economy</h2>
                <h3 class="text-3xl sm:text-4xl font-extrabold text-white">Flexible Lindr Coin Packages</h3>
                <p class="text-slate-400 text-sm sm:text-base">No hidden subscriptions. Purchase coins on demand via M-Pesa, Flutterwave, or KoraPay and spend them on your terms.</p>
            </div>

            <!-- Coin Packages Grid -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Package 1 -->
                <div class="p-6 rounded-2xl bg-[#111827] border border-slate-800 flex flex-col justify-between hover:border-slate-700 transition-all">
                    <div>
                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Starter</div>
                        <div class="text-3xl font-extrabold text-white mb-1">100 <span class="text-amber-400 text-sm">Coins</span></div>
                        <p class="text-xs text-slate-400 mb-6">Perfect for sending initial messages and trying out virtual gifts.</p>
                    </div>
                    <div class="text-xs font-bold text-slate-300 py-2 border-t border-slate-800">Instant Activation</div>
                </div>

                <!-- Package 2 -->
                <div class="p-6 rounded-2xl bg-[#111827] border border-rose-500/50 flex flex-col justify-between relative shadow-xl shadow-rose-950/20">
                    <div class="absolute -top-3 right-4 px-3 py-1 rounded-full bg-rose-500 text-white text-[10px] font-extrabold uppercase">Most Popular</div>
                    <div>
                        <div class="text-xs font-semibold text-rose-400 uppercase tracking-wider mb-2">Popular</div>
                        <div class="text-3xl font-extrabold text-white mb-1">500 <span class="text-amber-400 text-sm">Coins</span></div>
                        <p class="text-xs text-slate-400 mb-6">Great value for active daters looking to stay connected.</p>
                    </div>
                    <div class="text-xs font-bold text-rose-400 py-2 border-t border-slate-800">Includes Bonus Gifts</div>
                </div>

                <!-- Package 3 -->
                <div class="p-6 rounded-2xl bg-[#111827] border border-slate-800 flex flex-col justify-between hover:border-slate-700 transition-all">
                    <div>
                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Pro</div>
                        <div class="text-3xl font-extrabold text-white mb-1">1,200 <span class="text-amber-400 text-sm">Coins</span></div>
                        <p class="text-xs text-slate-400 mb-6">Unlocks high-tier virtual gifts and high-volume messaging.</p>
                    </div>
                    <div class="text-xs font-bold text-slate-300 py-2 border-t border-slate-800">VIP Priority Match</div>
                </div>

                <!-- Package 4 -->
                <div class="p-6 rounded-2xl bg-[#111827] border border-amber-500/40 flex flex-col justify-between hover:border-amber-500 transition-all">
                    <div>
                        <div class="text-xs font-semibold text-amber-400 uppercase tracking-wider mb-2">Ultimate</div>
                        <div class="text-3xl font-extrabold text-white mb-1">3,000 <span class="text-amber-400 text-sm">Coins</span></div>
                        <p class="text-xs text-slate-400 mb-6">Maximum savings for power users and top gift senders.</p>
                    </div>
                    <div class="text-xs font-bold text-amber-400 py-2 border-t border-slate-800">Best Value Tier</div>
                </div>
            </div>
        </div>
    </section>

    <!-- 7. Virtual Gifts Showcase -->
    <section class="py-20 bg-slate-900/40 border-y border-slate-800/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-6">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold">
                        Animated Appreciation
                    </div>
                    <h3 class="text-3xl sm:text-4xl font-extrabold text-white leading-tight">Delight Your Match with Digital Virtual Gifts</h3>
                    <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                        Say more than words can express. Choose from dozens of custom animated gifts—from red roses to gold crowns—to make every conversation memorable.
                    </p>
                    <ul class="space-y-3 text-xs sm:text-sm text-slate-300">
                        <li class="flex items-center gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center font-bold text-xs">✓</span>
                            <span>Instant in-chat gift animations</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center font-bold text-xs">✓</span>
                            <span>Direct coin value transfer to recipient</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center font-bold text-xs">✓</span>
                            <span>Exclusive limited-edition gift collection</span>
                        </li>
                    </ul>
                </div>

                <div class="flex justify-center">
                    <div class="rounded-2xl overflow-hidden border border-slate-800 max-w-md shadow-2xl">
                        <img src="{{ $media['gifts_screen'] ?? asset('storage/landing/gifts_screen.png') }}" alt="Virtual Gifts Grid" class="w-full h-auto">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 8. Female Creator Earnings & Withdrawals -->
    <section id="earnings" class="py-24 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-br from-[#111827] via-slate-900 to-slate-950 p-8 sm:p-12 rounded-3xl border border-slate-800 shadow-2xl relative overflow-hidden">
                <div class="grid lg:grid-cols-12 gap-8 items-center relative z-10">
                    <div class="lg:col-span-7 space-y-6">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-semibold">
                            Transparent Monetization
                        </div>
                        <h3 class="text-3xl sm:text-4xl font-extrabold text-white">Earn Real Value for Engaging & Gifting</h3>
                        <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                            Lindr rewards active female creators. Coins received from incoming messages and virtual gifts accumulate directly in your earnings wallet for instant payout.
                        </p>

                        <div class="grid sm:grid-cols-2 gap-4 pt-2">
                            <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                                <div class="text-amber-400 font-bold text-base mb-1">M-Pesa Direct</div>
                                <p class="text-xs text-slate-400">Withdraw earnings directly to Safaricom M-Pesa in Kenya.</p>
                            </div>
                            <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                                <div class="text-amber-400 font-bold text-base mb-1">Automated Processing</div>
                                <p class="text-xs text-slate-400">Payout requests are reviewed and disbursed rapidly.</p>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-5 flex justify-center">
                        <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 w-full max-w-sm space-y-4">
                            <div class="text-xs font-bold text-slate-400 uppercase">Creator Earnings Overview</div>
                            <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 flex justify-between items-center">
                                <div>
                                    <div class="text-[10px] text-slate-500">Withdrawable Balance</div>
                                    <div class="text-xl font-extrabold text-white">3,450 <span class="text-amber-400 text-xs">Coins</span></div>
                                </div>
                                <div class="px-3 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 text-xs font-bold">Ready</div>
                            </div>
                            <div class="text-[11px] text-slate-400 text-center">100% Payout Visibility • Zero Hidden Processing Fees</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 9. Multi-Gateway Financial Infrastructure -->
    <section class="py-16 bg-slate-900/40 border-y border-slate-800/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-8">
            <h4 class="text-xs font-bold uppercase tracking-widest text-slate-400">Supported Secure Payment Gateways</h4>
            <div class="flex flex-wrap items-center justify-center gap-8 sm:gap-16 opacity-80 filter grayscale hover:grayscale-0 transition-all">
                <div class="flex items-center gap-2 text-slate-300 font-bold text-lg sm:text-xl">
                    <span class="w-3 h-3 rounded-full bg-emerald-500"></span> M-Pesa Daraja
                </div>
                <div class="flex items-center gap-2 text-slate-300 font-bold text-lg sm:text-xl">
                    <span class="w-3 h-3 rounded-full bg-amber-500"></span> Flutterwave
                </div>
                <div class="flex items-center gap-2 text-slate-300 font-bold text-lg sm:text-xl">
                    <span class="w-3 h-3 rounded-full bg-blue-500"></span> KoraPay
                </div>
                <div class="flex items-center gap-2 text-slate-300 font-bold text-lg sm:text-xl">
                    <span class="w-3 h-3 rounded-full bg-purple-500"></span> Google Pay
                </div>
            </div>
        </div>
    </section>

    <!-- 10. Safety, Privacy & Verification -->
    <section id="safety" class="py-24 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="flex justify-center order-2 lg:order-1">
                    <div class="rounded-2xl overflow-hidden border border-slate-800 max-w-md shadow-2xl">
                        <img src="{{ $media['safety_illustration'] ?? asset('storage/landing/safety_illustration.png') }}" alt="Lindr Safety & Security" class="w-full h-auto">
                    </div>
                </div>

                <div class="space-y-6 order-1 lg:order-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-400 text-xs font-semibold">
                        Your Safety Comes First
                    </div>
                    <h3 class="text-3xl sm:text-4xl font-extrabold text-white leading-tight">Bank-Grade Protection & Safety Tools</h3>
                    <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                        Lindr enforces zero tolerance for harassment, fake profiles, or spam. Your personal contact information is never disclosed to other users.
                    </p>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="p-4 rounded-xl bg-[#111827] border border-slate-800">
                            <div class="text-white font-bold text-sm mb-1">1-Click Blocking</div>
                            <p class="text-xs text-slate-400">Instantly block any user from contacting you ever again.</p>
                        </div>
                        <div class="p-4 rounded-xl bg-[#111827] border border-slate-800">
                            <div class="text-white font-bold text-sm mb-1">Report Escalation</div>
                            <p class="text-xs text-slate-400">24/7 moderation team reviews reports and enforces bans.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 11. Mobile Experience Highlight -->
    <section class="py-20 bg-slate-900/40 border-y border-slate-800/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center max-w-3xl space-y-6">
            <h3 class="text-3xl font-extrabold text-white">Powered by Expo & React Native</h3>
            <p class="text-slate-300 text-sm sm:text-base">
                Enjoy a native app experience on both iOS and Android. Optimized for fast startup times, low data consumption, and instant push notifications.
            </p>
            <div class="flex justify-center">
                <img src="{{ $media['download_app_mockup'] ?? asset('storage/landing/download_app_mockup.png') }}" alt="Mobile Application Preview" class="w-full max-w-md rounded-2xl border border-slate-800 shadow-2xl">
            </div>
        </div>
    </section>

    <!-- 12. Frequently Asked Questions (FAQ Accordion with Alpine.js) -->
    <section id="faq" class="py-24 relative" x-data="{ activeFaq: null }">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-widest text-rose-400">Got Questions?</h2>
                <h3 class="text-3xl sm:text-4xl font-extrabold text-white">Frequently Asked Questions</h3>
            </div>

            <div class="space-y-4">
                @forelse($faqs as $index => $faq)
                    <div class="rounded-2xl bg-[#111827] border border-slate-800 overflow-hidden transition-colors">
                        <button x-on:click="activeFaq = activeFaq === {{ $index }} ? null : {{ $index }}"
                            class="w-full p-6 text-left flex items-center justify-between font-bold text-base text-white focus:outline-none">
                            <span>{{ data_get($faq, 'question') }}</span>
                            <svg class="w-5 h-5 text-rose-500 transform transition-transform duration-200" :class="{ 'rotate-180': activeFaq === {{ $index }} }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="activeFaq === {{ $index }}" x-cloak x-collapse class="px-6 pb-6 text-xs sm:text-sm text-slate-300 leading-relaxed border-t border-slate-800/60 pt-4">
                            {{ data_get($faq, 'answer') }}
                        </div>
                    </div>
                @empty
                    <div class="text-center text-slate-400 py-8">No FAQ items currently available.</div>
                @endforelse
            </div>
        </div>
    </section>

    <!-- 13. Final Download Call to Action (CTA) -->
    <section id="download" class="py-24 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-r from-rose-600 via-rose-500 to-amber-500 rounded-3xl p-8 sm:p-16 text-center text-white relative overflow-hidden shadow-2xl">
                <div class="max-w-2xl mx-auto space-y-6 relative z-10">
                    <h3 class="text-3xl sm:text-5xl font-black tracking-tight leading-tight">Ready to Find Your Match?</h3>
                    <p class="text-rose-100 text-sm sm:text-lg">Download Lindr today and start building genuine social connections instantly.</p>

                    <div class="flex flex-wrap justify-center gap-4 pt-4">
                        @if(!empty($settings['app_store_url']))
                            <a href="{{ $settings['app_store_url'] }}" target="_blank" rel="noopener" class="px-8 py-4 rounded-xl bg-slate-950 text-white font-bold text-sm shadow-xl hover:bg-slate-900 transition-colors">
                                Download on App Store
                            </a>
                        @endif

                        @if(!empty($settings['google_play_url']))
                            <a href="{{ $settings['google_play_url'] }}" target="_blank" rel="noopener" class="px-8 py-4 rounded-xl bg-slate-950 text-white font-bold text-sm shadow-xl hover:bg-slate-900 transition-colors">
                                Get it on Google Play
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 14. Protected Admin Access Notice Section -->
    <section class="py-8 bg-slate-950 border-t border-slate-900 text-center">
        <div class="max-w-7xl mx-auto px-4 text-xs text-slate-500 flex items-center justify-center gap-2">
            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <span>Lindr System Administration Entrance:</span>
            <a href="/access" class="text-amber-400 underline hover:text-amber-300 font-medium">System Admin Access Portal (/access)</a>
        </div>
    </section>

    <!-- 15. Comprehensive Footer -->
    <footer class="bg-[#070A11] border-t border-slate-800/80 pt-16 pb-12 text-slate-400 text-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <!-- Col 1: Brand -->
                <div class="col-span-2 md:col-span-1 space-y-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-rose-500 to-amber-400 flex items-center justify-center text-white font-bold text-sm">
                            ♥
                        </div>
                        <span class="text-lg font-bold text-white">Lindr</span>
                    </div>
                    <p class="text-slate-400 text-xs leading-relaxed">
                        Modern social dating platform connecting verified users with tokenized chat, virtual gifts, and instant payout infrastructure.
                    </p>
                </div>

                <!-- Col 2: Product Links -->
                <div class="space-y-3">
                    <div class="text-xs font-bold text-white uppercase tracking-wider">Product</div>
                    <ul class="space-y-2">
                        <li><a href="#features" class="hover:text-white transition-colors">Features</a></li>
                        <li><a href="#how-it-works" class="hover:text-white transition-colors">How It Works</a></li>
                        <li><a href="#coins" class="hover:text-white transition-colors">Coins & Packages</a></li>
                        <li><a href="#earnings" class="hover:text-white transition-colors">Creator Earnings</a></li>
                    </ul>
                </div>

                <!-- Col 3: Safety & Legal -->
                <div class="space-y-3">
                    <div class="text-xs font-bold text-white uppercase tracking-wider">Trust & Legal</div>
                    <ul class="space-y-2">
                        <li><a href="#safety" class="hover:text-white transition-colors">Safety Center</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Terms of Service</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Community Guidelines</a></li>
                    </ul>
                </div>

                <!-- Col 4: Contact & System Status -->
                <div class="space-y-3">
                    <div class="text-xs font-bold text-white uppercase tracking-wider">Contact & Support</div>
                    <p class="text-slate-400">Questions? Reach out to support:</p>
                    <a href="mailto:{{ $settings['contact_email'] ?? 'support@lindr.app' }}" class="text-rose-400 hover:underline font-medium block">
                        {{ $settings['contact_email'] ?? 'support@lindr.app' }}
                    </a>
                    <div class="pt-2 flex items-center gap-2 text-emerald-400 font-medium">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>API Systems 100% Operational</span>
                    </div>
                </div>
            </div>

            <!-- Bottom Copyright bar -->
            <div class="pt-8 border-t border-slate-800/60 flex flex-col sm:flex-row items-center justify-between gap-4 text-slate-500">
                <p>&copy; {{ date('Y') }} {{ $settings['app_name'] ?? 'Lindr' }} Inc. All rights reserved.</p>
                <div class="flex items-center gap-4">
                    <a href="/access" class="hover:text-slate-300 transition-colors">Admin Portal</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
