<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- SEO Primary Meta Tags -->
    <title>{{ $settings['site_title'] ?? 'Lindr — Meet. Connect. Go Live.' }}</title>
    <meta name="description" content="{{ $settings['meta_description'] ?? 'Discover people, connect through Match and Discover, chat, send gifts and enjoy live video conversations on Lindr.' }}">
    <meta name="keywords" content="{{ $settings['meta_keywords'] ?? 'social discovery, live video calls, match, discover people, virtual gifts, lindr app, tokens, creator earnings, m-pesa' }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://lindrapp.top">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://lindrapp.top">
    <meta property="og:title" content="{{ $settings['site_title'] ?? 'Lindr — Meet. Connect. Go Live.' }}">
    <meta property="og:description" content="{{ $settings['meta_description'] ?? 'Discover people, connect through Match and Discover, chat, send gifts and enjoy live video conversations on Lindr.' }}">
    <meta property="og:image" content="{{ $media['hero_lifestyle'] ?? asset('storage/landing/hero_lifestyle.png') }}">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://lindrapp.top">
    <meta name="twitter:title" content="{{ $settings['site_title'] ?? 'Lindr — Meet. Connect. Go Live.' }}">
    <meta name="twitter:description" content="{{ $settings['meta_description'] ?? 'Discover people, connect through Match and Discover, chat, send gifts and enjoy live video conversations on Lindr.' }}">
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
      "description": "Social discovery and live video connection platform with Match, Discover, chat, digital gifts, and creator withdrawals.",
      "publisher": {
        "@@type": "Organization",
        "name": "Lindr Inc.",
        "url": "https://lindrapp.top"
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
            <nav class="hidden md:flex items-center gap-6 lg:gap-8 text-sm font-medium text-slate-300">
                <a href="#match" class="hover:text-rose-400 transition-colors">Match</a>
                <a href="#discover" class="hover:text-rose-400 transition-colors">Discover</a>
                <a href="#live-video" class="hover:text-rose-400 transition-colors">Live Video</a>
                <a href="#tokens" class="hover:text-rose-400 transition-colors">Tokens</a>
                <a href="#creators" class="hover:text-rose-400 transition-colors">Creators</a>
                <a href="#spotlight" class="hover:text-rose-400 transition-colors">Spotlight</a>
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

                <a href="#download" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-rose-500 to-amber-500 hover:from-rose-600 hover:to-amber-600 text-white font-semibold text-xs shadow-lg shadow-rose-500/25 hover:shadow-rose-500/40 transition-all">
                    <span>Get Started</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </a>
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
                        {{ $settings['hero_headline'] ?? 'Meet. Connect. Go Live.' }}
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-white leading-[1.1]">
                        Meet. Connect. <br class="hidden sm:inline"/>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-rose-400 via-amber-300 to-amber-500">Go Live.</span>
                    </h1>

                    <p class="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto lg:mx-0 font-normal leading-relaxed">
                        {{ $settings['hero_description'] ?? 'Discover real people, connect instantly and turn conversations into live video experiences.' }}
                    </p>

                    <!-- Download Buttons & CTA -->
                    <div class="flex flex-wrap items-center justify-center lg:justify-start gap-4 pt-2">
                        <a href="#download" class="inline-flex items-center gap-2 px-7 py-3.5 rounded-xl bg-gradient-to-r from-rose-500 to-amber-500 hover:from-rose-600 hover:to-amber-600 text-white font-bold text-sm transition-all shadow-xl shadow-rose-500/20">
                            <span>Get Started</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </a>

                        <a href="#how-it-works" class="inline-flex items-center gap-2 px-6 py-3.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 text-slate-200 font-medium text-sm transition-all">
                            <span>Explore Lindr</span>
                        </a>
                    </div>
                </div>

                <!-- Right Visual Column -->
                <div class="lg:col-span-5 relative flex justify-center">
                    <div class="relative w-full max-w-sm sm:max-w-md">
                        <!-- Main Lifestyle Background Card -->
                        <div class="rounded-3xl overflow-hidden border border-slate-800 shadow-2xl shadow-rose-950/40 relative group">
                            <img src="{{ $media['hero_lifestyle'] ?? asset('storage/landing/hero_lifestyle.png') }}" alt="Lindr Live Video Connections" class="w-full h-[420px] object-cover filter contrast-[1.05]">
                            <div class="absolute inset-0 bg-gradient-to-t from-[#0B0F19] via-transparent to-transparent"></div>
                        </div>

                        <!-- Phone Mockup Overlay -->
                        <div class="absolute -bottom-8 -right-4 sm:-right-8 w-48 sm:w-56 rounded-2xl overflow-hidden border-2 border-slate-700/80 shadow-2xl bg-slate-900 transform hover:scale-105 transition-transform duration-300">
                            <img src="{{ $media['hero_phone_mockup'] ?? asset('storage/landing/hero_phone_mockup.png') }}" alt="Lindr Mobile App Interface" class="w-full h-auto">
                        </div>

                        <!-- Floating Glassmorphism Badge 1 -->
                        <div class="absolute -top-6 -left-6 bg-slate-900/90 backdrop-blur-xl border border-rose-500/30 px-4 py-3 rounded-2xl shadow-xl flex items-center gap-3 animate-bounce" style="animation-duration: 4s;">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-r from-rose-500 to-amber-400 flex items-center justify-center text-white text-xs font-bold">
                                ♥
                            </div>
                            <div>
                                <div class="text-xs font-bold text-white">Match & Discover</div>
                                <div class="text-[10px] text-slate-400">Creator Verification</div>
                            </div>
                        </div>

                        <!-- Floating Glassmorphism Badge 2 -->
                        <div class="absolute bottom-12 -left-8 bg-slate-900/90 backdrop-blur-xl border border-amber-500/30 px-4 py-3 rounded-2xl shadow-xl flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-amber-500/20 text-amber-400 flex items-center justify-center text-xs">
                                ⚡
                            </div>
                            <div>
                                <div class="text-xs font-bold text-white">Creator Withdrawals</div>
                                <div class="text-[10px] text-slate-400">M-Pesa & Supported Gateways</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Match Section -->
    <section id="match" class="py-20 bg-slate-900/40 border-y border-slate-800/60 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-6">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold">
                        Instant Live Connections
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white leading-tight">Match Instantly</h2>
                    <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                        Tap Match and let Lindr find an available person for you. When someone accepts your connection request, you're instantly matched and can start a live video conversation.
                    </p>

                    <div class="space-y-4 pt-2">
                        <div class="flex items-start gap-3 p-4 rounded-xl bg-[#111827] border border-slate-800">
                            <div class="w-7 h-7 rounded-lg bg-rose-500/20 text-rose-400 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">1</div>
                            <div>
                                <h4 class="text-sm font-bold text-white mb-0.5">Instant Request Routing</h4>
                                <p class="text-xs text-slate-400">Match routes your connection request to available, eligible people on the platform.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-4 rounded-xl bg-[#111827] border border-slate-800">
                            <div class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">2</div>
                            <div>
                                <h4 class="text-sm font-bold text-white mb-0.5">First to Accept Connects</h4>
                                <p class="text-xs text-slate-400">The first eligible person to accept becomes your connection instantly.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-4 rounded-xl bg-[#111827] border border-slate-800">
                            <div class="w-7 h-7 rounded-lg bg-purple-500/20 text-purple-400 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">3</div>
                            <div>
                                <h4 class="text-sm font-bold text-white mb-0.5">Live Video Conversation</h4>
                                <p class="text-xs text-slate-400">Your live video call begins right after connection for a face-to-face experience.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center">
                    <div class="bg-[#111827] rounded-3xl border border-slate-800 p-6 max-w-md w-full shadow-2xl hover:border-rose-500/40 transition-colors">
                        <div class="rounded-2xl overflow-hidden mb-4 bg-slate-900 border border-slate-800">
                            <img src="{{ $media['match_screen'] ?? asset('storage/landing/match_screen.png') }}" alt="Match Connection Screen" class="w-full h-80 object-cover">
                        </div>
                        <h4 class="text-base font-bold text-white text-center mb-1">Instant Match Request</h4>
                        <p class="text-xs text-slate-400 text-center">Connect directly with active available users with a single tap.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. Discover Section -->
    <section id="discover" class="py-24 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-widest text-amber-400">Explore Active People</h2>
                <h3 class="text-3xl sm:text-4xl font-extrabold text-white">Discover People</h3>
                <p class="text-slate-400 text-sm sm:text-base">Browse active profiles, explore interests, see who's available and choose who you'd like to connect with.</p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Highlight 1 -->
                <div class="p-8 rounded-2xl bg-[#111827] border border-slate-800 hover:border-rose-500/40 transition-all duration-300">
                    <div class="w-12 h-12 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <h4 class="text-xl font-bold text-white mb-3">Detailed Profiles & Photos</h4>
                    <p class="text-slate-400 text-sm leading-relaxed">Explore profile photos, age, country, interests, and profile information to find people you resonate with.</p>
                </div>

                <!-- Highlight 2 -->
                <div class="p-8 rounded-2xl bg-[#111827] border border-slate-800 hover:border-amber-500/40 transition-all duration-300">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728M9.172 14.828a4 4 0 010-5.656m5.656 0a4 4 0 010 5.656M12 12h.01"/>
                        </svg>
                    </div>
                    <h4 class="text-xl font-bold text-white mb-3">Live Active Status</h4>
                    <p class="text-slate-400 text-sm leading-relaxed">See who is currently active and available on the platform so you can initiate timely connections.</p>
                </div>

                <!-- Highlight 3 -->
                <div class="p-8 rounded-2xl bg-[#111827] border border-slate-800 hover:border-purple-500/40 transition-all duration-300">
                    <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <h4 class="text-xl font-bold text-white mb-3">Creator Verification Badges</h4>
                    <p class="text-slate-400 text-sm leading-relaxed">Identify verified creators who have completed selfie and liveness verification for enhanced authenticity.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 5. Live Video Calling Section -->
    <section id="live-video" class="py-20 bg-slate-900/40 border-y border-slate-800/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="flex justify-center order-2 lg:order-1">
                    <div class="bg-[#111827] rounded-3xl border border-slate-800 p-6 max-w-md w-full shadow-2xl">
                        <div class="rounded-2xl overflow-hidden mb-4 bg-slate-900 border border-slate-800">
                            <img src="{{ $media['discover_screen'] ?? asset('storage/landing/discover_screen.png') }}" alt="Live Video Calling Interface" class="w-full h-80 object-cover">
                        </div>
                        <h4 class="text-base font-bold text-white text-center mb-1">Real-Time Face-to-Face Video</h4>
                        <p class="text-xs text-slate-400 text-center">Crystal-clear video powered by Lindr platform infrastructure.</p>
                    </div>
                </div>

                <div class="space-y-6 order-1 lg:order-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-400 text-xs font-semibold">
                        Real-Time Interactivity
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white leading-tight">Talk Face to Face</h2>
                    <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                        Connect through live video and have real-time conversations with people you meet on Lindr.
                    </p>
                    <ul class="space-y-3 text-xs sm:text-sm text-slate-300">
                        <li class="flex items-center gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-purple-500/20 text-purple-400 flex items-center justify-center font-bold text-xs">✓</span>
                            <span>Initiate live video calls through eligible connections</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-purple-500/20 text-purple-400 flex items-center justify-center font-bold text-xs">✓</span>
                            <span>Calls use Lindr Tokens where applicable</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-purple-500/20 text-purple-400 flex items-center justify-center font-bold text-xs">✓</span>
                            <span>Creator earnings seamlessly accumulated on eligible calls</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. Tokens Economy Section -->
    <section id="tokens" class="py-24 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-widest text-amber-400">Platform Economy</h2>
                <h3 class="text-3xl sm:text-4xl font-extrabold text-white">Lindr Tokens</h3>
                <p class="text-slate-400 text-sm sm:text-base">Use Lindr Tokens for Match, video calls, gifts and other premium interactions.</p>
            </div>

            <!-- Token Package Tiers -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Tier 1 -->
                <div class="p-6 rounded-2xl bg-[#111827] border border-slate-800 flex flex-col justify-between hover:border-slate-700 transition-all">
                    <div>
                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Starter Pack</div>
                        <div class="text-3xl font-extrabold text-white mb-1">Tokens</div>
                        <p class="text-xs text-slate-400 mb-6">Ideal for making initial Match requests and trying out digital gifts.</p>
                    </div>
                    <div class="text-xs font-bold text-slate-300 py-2 border-t border-slate-800">Instant Wallet Top-Up</div>
                </div>

                <!-- Tier 2 -->
                <div class="p-6 rounded-2xl bg-[#111827] border border-rose-500/50 flex flex-col justify-between relative shadow-xl shadow-rose-950/20">
                    <div class="absolute -top-3 right-4 px-3 py-1 rounded-full bg-rose-500 text-white text-[10px] font-extrabold uppercase">Popular</div>
                    <div>
                        <div class="text-xs font-semibold text-rose-400 uppercase tracking-wider mb-2">Popular Pack</div>
                        <div class="text-3xl font-extrabold text-white mb-1">Tokens</div>
                        <p class="text-xs text-slate-400 mb-6">Great value for regular live video calls and sending digital gifts.</p>
                    </div>
                    <div class="text-xs font-bold text-rose-400 py-2 border-t border-slate-800">Best For Video Calls</div>
                </div>

                <!-- Tier 3 -->
                <div class="p-6 rounded-2xl bg-[#111827] border border-slate-800 flex flex-col justify-between hover:border-slate-700 transition-all">
                    <div>
                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Pro Pack</div>
                        <div class="text-3xl font-extrabold text-white mb-1">Tokens</div>
                        <p class="text-xs text-slate-400 mb-6">Designed for active users engaging in frequent conversations and gifts.</p>
                    </div>
                    <div class="text-xs font-bold text-slate-300 py-2 border-t border-slate-800">High Volume Messaging</div>
                </div>

                <!-- Tier 4 -->
                <div class="p-6 rounded-2xl bg-[#111827] border border-amber-500/40 flex flex-col justify-between hover:border-amber-500 transition-all">
                    <div>
                        <div class="text-xs font-semibold text-amber-400 uppercase tracking-wider mb-2">Ultimate Pack</div>
                        <div class="text-3xl font-extrabold text-white mb-1">Tokens</div>
                        <p class="text-xs text-slate-400 mb-6">Maximum value tier for power users and top gift supporters.</p>
                    </div>
                    <div class="text-xs font-bold text-amber-400 py-2 border-t border-slate-800">Best Value Tier</div>
                </div>
            </div>
        </div>
    </section>

    <!-- 7. Earn as a Verified Creator Section -->
    <section id="creators" class="py-24 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-br from-[#111827] via-slate-900 to-slate-950 p-8 sm:p-12 rounded-3xl border border-slate-800 shadow-2xl relative overflow-hidden">
                <div class="grid lg:grid-cols-12 gap-8 items-center relative z-10">
                    <div class="lg:col-span-7 space-y-6">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-semibold">
                            Creator Earnings
                        </div>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Earn as a Verified Creator</h2>
                        <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                            Verified creators can earn Credits from eligible monetized interactions such as video calls and gifts. Both verified women and verified men can complete creator verification and earn on Lindr.
                        </p>

                        <div class="grid sm:grid-cols-2 gap-4 pt-2">
                            <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                                <div class="text-amber-400 font-bold text-sm mb-1">Tokens vs Credits</div>
                                <p class="text-xs text-slate-400">Users spend <strong class="text-slate-200">Tokens</strong> for interactions. Verified creators earn <strong class="text-slate-200">Credits</strong> from eligible calls and gifts.</p>
                            </div>
                            <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                                <div class="text-amber-400 font-bold text-sm mb-1">Gender-Inclusive Eligibility</div>
                                <p class="text-xs text-slate-400">Both verified female and male creators can complete liveness verification and earn.</p>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-5 flex justify-center">
                        <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 w-full max-w-sm space-y-4">
                            <div class="text-xs font-bold text-slate-400 uppercase">Creator Earnings Model</div>
                            <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-slate-400">User Spends:</span>
                                    <span class="text-xs font-bold text-amber-400">Tokens</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-slate-400">Creator Earns:</span>
                                    <span class="text-xs font-bold text-emerald-400">Credits</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-slate-400">Verification:</span>
                                    <span class="text-xs font-bold text-purple-400">Selfie Liveness</span>
                                </div>
                            </div>
                            <div class="text-[11px] text-slate-400 text-center">Transparent Platform Credit Accumulation</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 8. Send Digital Gifts Section -->
    <section id="gifts" class="py-20 bg-slate-900/40 border-y border-slate-800/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-6">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold">
                        Digital Expressions
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white leading-tight">Send Gifts</h2>
                    <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                        Show appreciation with digital gifts during your Lindr experience.
                    </p>
                    <ul class="space-y-3 text-xs sm:text-sm text-slate-300">
                        <li class="flex items-center gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center font-bold text-xs">✓</span>
                            <span>Users spend Tokens to send digital gifts</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center font-bold text-xs">✓</span>
                            <span>Verified creators can earn Credits from eligible gifts</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center font-bold text-xs">✓</span>
                            <span>Custom animated gift catalogue inside chat and video calls</span>
                        </li>
                    </ul>
                </div>

                <div class="flex justify-center">
                    <div class="rounded-2xl overflow-hidden border border-slate-800 max-w-md shadow-2xl">
                        <img src="{{ $media['gifts_screen'] ?? asset('storage/landing/gifts_screen.png') }}" alt="Digital Gifts Catalogue" class="w-full h-auto">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 9. Chat & Connect Section -->
    <section id="chat" class="py-24 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="flex justify-center order-2 lg:order-1">
                    <div class="bg-[#111827] rounded-3xl border border-slate-800 p-6 max-w-md w-full shadow-2xl">
                        <div class="rounded-2xl overflow-hidden mb-4 bg-slate-900 border border-slate-800">
                            <img src="{{ $media['chat_screen'] ?? asset('storage/landing/chat_screen.png') }}" alt="Chat & Connect Interface" class="w-full h-80 object-cover">
                        </div>
                        <h4 class="text-base font-bold text-white text-center mb-1">Direct 1-on-1 Messaging</h4>
                        <p class="text-xs text-slate-400 text-center">Send private messages and connect seamlessly.</p>
                    </div>
                </div>

                <div class="space-y-6 order-1 lg:order-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-semibold">
                        Private Messaging
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white leading-tight">Chat & Connect</h2>
                    <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                        Start conversations, exchange messages and stay connected with people you discover on Lindr.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 10. Spotlight & Levels Section -->
    <section id="spotlight" class="py-20 bg-slate-900/40 border-y border-slate-800/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-8">
                <!-- Spotlight Card -->
                <div class="p-8 rounded-2xl bg-[#111827] border border-slate-800 hover:border-amber-500/40 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mb-6 font-bold">
                        ⚡
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-3">Get Seen With Lindr Spotlight</h3>
                    <p class="text-slate-400 text-sm leading-relaxed mb-4">
                        Boost your visibility across eligible discovery surfaces for a limited period to increase your profile exposure.
                    </p>
                    <p class="text-xs text-slate-500">
                        *Spotlight enhances discovery exposure across eligible surfaces without bypassing safety or moderation rules.
                    </p>
                </div>

                <!-- Levels Card -->
                <div id="levels" class="p-8 rounded-2xl bg-[#111827] border border-slate-800 hover:border-rose-500/40 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center mb-6 font-bold">
                        ★
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-3">Lindr Levels</h3>
                    <p class="text-slate-400 text-sm leading-relaxed mb-4">
                        Build your Lindr profile and activity over time to unlock greater exposure and benefits on the platform.
                    </p>
                    <p class="text-xs text-slate-500">
                        *Progression is tied to profile completion and genuine platform engagement.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 11. Creator Withdrawals Section -->
    <section id="withdrawals" class="py-24 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center max-w-3xl space-y-6">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold">
                Financial Infrastructure
            </div>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Creator Withdrawals</h2>
            <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                Verified creators can redeem eligible Credits through supported withdrawal methods. Kenyan creators can use supported M-Pesa withdrawal functionality.
            </p>

            <div class="flex flex-wrap items-center justify-center gap-8 pt-4">
                <div class="flex items-center gap-2 text-slate-300 font-bold text-base">
                    <span class="w-3 h-3 rounded-full bg-emerald-500"></span> M-Pesa (Kenya)
                </div>
                <div class="flex items-center gap-2 text-slate-300 font-bold text-base">
                    <span class="w-3 h-3 rounded-full bg-amber-500"></span> Flutterwave
                </div>
                <div class="flex items-center gap-2 text-slate-300 font-bold text-base">
                    <span class="w-3 h-3 rounded-full bg-blue-500"></span> KoraPay
                </div>
            </div>
        </div>
    </section>

    <!-- 12. Safety Section -->
    <section id="safety" class="py-24 bg-slate-900/40 border-y border-slate-800/60 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="flex justify-center order-2 lg:order-1">
                    <div class="rounded-2xl overflow-hidden border border-slate-800 max-w-md shadow-2xl">
                        <img src="{{ $media['safety_illustration'] ?? asset('storage/landing/safety_illustration.png') }}" alt="Lindr Community Safety" class="w-full h-auto">
                    </div>
                </div>

                <div class="space-y-6 order-1 lg:order-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-400 text-xs font-semibold">
                        Community Safety
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white leading-tight">Built With Community Safety In Mind</h2>
                    <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                        Lindr uses verification, reporting, blocking and moderation tools to help keep the community safer.
                    </p>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="p-4 rounded-xl bg-[#111827] border border-slate-800">
                            <div class="text-white font-bold text-sm mb-1">Creator Verification</div>
                            <p class="text-xs text-slate-400">Creators complete selfie/liveness verification before they can earn.</p>
                        </div>
                        <div class="p-4 rounded-xl bg-[#111827] border border-slate-800">
                            <div class="text-white font-bold text-sm mb-1">1-Click Blocking</div>
                            <p class="text-xs text-slate-400">Instantly block any user from contacting you ever again.</p>
                        </div>
                        <div class="p-4 rounded-xl bg-[#111827] border border-slate-800">
                            <div class="text-white font-bold text-sm mb-1">Report Escalation</div>
                            <p class="text-xs text-slate-400">Reporting features allow rapid review of inappropriate behavior.</p>
                        </div>
                        <div class="p-4 rounded-xl bg-[#111827] border border-slate-800">
                            <div class="text-white font-bold text-sm mb-1">Community Guidelines</div>
                            <p class="text-xs text-slate-400">Enforced guidelines maintain a respectful user environment.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 13. How Lindr Works Section -->
    <section id="how-it-works" class="py-24 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-widest text-rose-400">Simple Experience</h2>
                <h3 class="text-3xl sm:text-4xl font-extrabold text-white">How Lindr Works</h3>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Step 1 -->
                <div class="p-6 rounded-2xl bg-[#111827] border border-slate-800">
                    <div class="text-3xl font-black text-rose-500/40 mb-3">01</div>
                    <h4 class="text-lg font-bold text-white mb-2">Sign Up</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">Join Lindr with Google and complete your profile.</p>
                </div>

                <!-- Step 2 -->
                <div class="p-6 rounded-2xl bg-[#111827] border border-slate-800">
                    <div class="text-3xl font-black text-amber-500/40 mb-3">02</div>
                    <h4 class="text-lg font-bold text-white mb-2">Discover</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">Explore active people and their interests.</p>
                </div>

                <!-- Step 3 -->
                <div class="p-6 rounded-2xl bg-[#111827] border border-slate-800">
                    <div class="text-3xl font-black text-purple-500/40 mb-3">03</div>
                    <h4 class="text-lg font-bold text-white mb-2">Connect</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">Use Match, chat, gifts or video calls to connect.</p>
                </div>

                <!-- Step 4 -->
                <div class="p-6 rounded-2xl bg-[#111827] border border-slate-800">
                    <div class="text-3xl font-black text-emerald-500/40 mb-3">04</div>
                    <h4 class="text-lg font-bold text-white mb-2">Go Live</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">Have real-time video conversations with your connections.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 14. Frequently Asked Questions (FAQ Accordion with Alpine.js) -->
    <section id="faq" class="py-24 bg-slate-900/40 border-y border-slate-800/60 relative" x-data="{ activeFaq: null }">
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

    <!-- 15. Final Call to Action (CTA) -->
    <section id="download" class="py-24 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-r from-rose-600 via-rose-500 to-amber-500 rounded-3xl p-8 sm:p-16 text-center text-white relative overflow-hidden shadow-2xl">
                <div class="max-w-2xl mx-auto space-y-6 relative z-10">
                    <h3 class="text-3xl sm:text-5xl font-black tracking-tight leading-tight">Meet. Connect. Go Live.</h3>
                    <p class="text-rose-100 text-sm sm:text-lg">Discover real people, connect instantly and turn conversations into live video experiences.</p>

                    <div class="flex flex-wrap justify-center gap-4 pt-4">
                        <a href="#download" class="px-8 py-4 rounded-xl bg-slate-950 text-white font-bold text-sm shadow-xl hover:bg-slate-900 transition-colors">
                            Get Started on Lindr
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 16. Protected Admin Access Notice Section -->
    <section class="py-8 bg-slate-950 border-t border-slate-900 text-center">
        <div class="max-w-7xl mx-auto px-4 text-xs text-slate-500 flex items-center justify-center gap-2">
            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <span>Lindr System Administration Entrance:</span>
            <a href="/access" class="text-amber-400 underline hover:text-amber-300 font-medium">System Admin Access Portal (/access)</a>
        </div>
    </section>

    <!-- 17. Comprehensive Footer -->
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
                        Social discovery and live video connection platform. Connect through Match or Discover, chat, send gifts, and have live video calls.
                    </p>
                </div>

                <!-- Col 2: Product Links -->
                <div class="space-y-3">
                    <div class="text-xs font-bold text-white uppercase tracking-wider">Product</div>
                    <ul class="space-y-2">
                        <li><a href="#match" class="hover:text-white transition-colors">Match</a></li>
                        <li><a href="#discover" class="hover:text-white transition-colors">Discover</a></li>
                        <li><a href="#live-video" class="hover:text-white transition-colors">Live Video</a></li>
                        <li><a href="#tokens" class="hover:text-white transition-colors">Tokens</a></li>
                        <li><a href="#creators" class="hover:text-white transition-colors">Creators</a></li>
                    </ul>
                </div>

                <!-- Col 3: Trust & Safety -->
                <div class="space-y-3">
                    <div class="text-xs font-bold text-white uppercase tracking-wider">Trust & Legal</div>
                    <ul class="space-y-2">
                        <li><a href="#safety" class="hover:text-white transition-colors">Safety Center</a></li>
                        <li><a href="#withdrawals" class="hover:text-white transition-colors">Creator Withdrawals</a></li>
                        <li><a href="#faq" class="hover:text-white transition-colors">FAQ</a></li>
                        <li><a href="/access" class="hover:text-white transition-colors">Admin Access</a></li>
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
                        <span>API Systems Operational</span>
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
