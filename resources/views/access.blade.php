<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#0B0F19]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Access Portal — Lindr</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased text-slate-100 selection:bg-rose-500 selection:text-white flex flex-col justify-between relative overflow-hidden bg-[#0B0F19]">

    <!-- Background Decorative Glows -->
    <div class="pointer-events-none absolute -top-40 -left-40 w-96 h-96 bg-rose-500/15 rounded-full blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-40 -right-40 w-96 h-96 bg-amber-500/15 rounded-full blur-3xl"></div>

    <!-- Header Navigation -->
    <header class="py-6 px-6 sm:px-12 flex items-center justify-between z-10">
        <a href="/" class="flex items-center gap-2.5 group">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-rose-500 to-amber-400 p-0.5 shadow-lg shadow-rose-500/25">
                <div class="w-full h-full bg-[#0B0F19] rounded-[10px] flex items-center justify-center">
                    <svg class="w-5 h-5 text-rose-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                    </svg>
                </div>
            </div>
            <span class="text-xl font-bold tracking-tight text-white group-hover:text-rose-400 transition-colors">Lindr</span>
        </a>

        <a href="/" class="text-xs font-medium text-slate-400 hover:text-slate-200 transition-colors">
            ← Back to Public Website
        </a>
    </header>

    <!-- Main Content Card -->
    <main class="flex-grow flex items-center justify-center px-4 py-12 z-10">
        <div class="w-full max-w-md bg-[#111827]/80 backdrop-blur-xl border border-slate-800/80 rounded-2xl p-8 shadow-2xl shadow-rose-950/30">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-tr from-rose-500/20 to-amber-500/20 border border-rose-500/30 mb-4 text-rose-400">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-white mb-2">Admin Access Portal</h1>
                <p class="text-xs text-slate-400 max-w-xs mx-auto">
                    Protected administration entrance. Mobile app users access Lindr via the mobile application.
                </p>
            </div>

            <!-- Error Banner -->
            @if ($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs flex flex-col gap-1">
                    @foreach ($errors->all() as $error)
                        <p class="flex items-center gap-1.5 font-medium">
                            <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ $error }}
                        </p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('access.login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Administrator Email
                    </label>
                    <input id="email" type="email" name="email" value="{{ old('email', 'admin@lindr.app') }}" required autofocus
                        class="w-full px-4 py-3 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white placeholder-slate-500 text-sm focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500 transition-colors">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                            Password
                        </label>
                    </div>
                    <input id="password" type="password" name="password" value="password" required
                        class="w-full px-4 py-3 rounded-xl bg-slate-900/90 border border-slate-700/80 text-white placeholder-slate-500 text-sm focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500 transition-colors">
                </div>

                <div class="flex items-center justify-between text-xs text-slate-400">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" checked class="rounded border-slate-700 bg-slate-900 text-rose-500 focus:ring-rose-500/20">
                        <span>Remember session</span>
                    </label>
                </div>

                <button type="submit"
                    class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-semibold text-sm shadow-lg shadow-rose-500/25 hover:shadow-rose-500/40 focus:outline-none focus:ring-2 focus:ring-rose-500/50 transition-all duration-200">
                    Sign In to Admin Dashboard
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-slate-800/80 text-center">
                <p class="text-[11px] text-slate-500">
                    Demo Credentials: <span class="font-mono text-slate-300">admin@lindr.app</span> / <span class="font-mono text-slate-300">password</span>
                </p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-6 px-6 text-center text-xs text-slate-500 z-10">
        <p>&copy; {{ date('Y') }} Lindr Inc. All rights reserved. Strictly Authorized Administrator Entrance.</p>
    </footer>

</body>
</html>
