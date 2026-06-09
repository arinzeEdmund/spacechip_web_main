@php
    $user = Auth::user();
    $fullName = trim((string) (($user->first_name ?? '').' '.($user->last_name ?? '')));
    if ($fullName === '') {
        $fullName = trim((string) ($user->name ?? ''));
    }
    $parts = preg_split('/\s+/', $fullName) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $initials .= mb_substr((string) $p, 0, 1);
    }
    $initials = mb_strtoupper($initials);
@endphp

@if (request()->routeIs('dashboard'))
    <nav class="sticky top-0 z-50 border-b border-gray-100 dark:border-white/10 bg-white/78 dark:bg-slate-900/78 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center flex-wrap gap-3 py-3">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-gradient-to-r from-[#f27457] to-[#145454] flex items-center justify-center text-white font-extrabold tracking-wide">
                        SC
                    </div>
                    <div class="font-extrabold text-[#145454] dark:text-white tracking-wide">
                        spacechip
                    </div>
                </a>

                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="{{ route('dashboard.virtual.index') }}" class="px-3.5 py-2.5 rounded-full bg-white/55 dark:bg-white/10 border border-black/10 dark:border-white/10 font-extrabold text-gray-700 dark:text-gray-200 flex items-center gap-2 transition-all hover:bg-white/70 dark:hover:bg-white/20">
                        <span class="hidden sm:inline">Numbers</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    @if ((bool) ($user?->is_admin ?? false) || in_array(mb_strtolower((string) ($user?->email ?? '')), array_values(array_filter(array_map(fn ($v) => trim(mb_strtolower($v)), explode(',', (string) (env('ADMIN_EMAILS', '') ?: ''))))), true))
                        <a href="{{ route('admin.dashboard') }}" class="px-3.5 py-2.5 rounded-full bg-white/55 dark:bg-white/10 border border-black/10 dark:border-white/10 font-extrabold text-gray-700 dark:text-gray-200 flex items-center gap-2 transition-all hover:bg-white/70 dark:hover:bg-white/20">
                            <span class="hidden sm:inline">Admin</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2l7 4v6c0 5-3 9-7 10-4-1-7-5-7-10V6l7-4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    @endif
                    <div class="hidden sm:flex items-center gap-3 p-2 rounded-2xl border border-black/5 dark:border-white/10 bg-black/5 dark:bg-white/5">
                        <div class="h-10 w-10 rounded-full bg-white/20 dark:bg-white/10 border border-white/20 flex items-center justify-center text-gray-700 dark:text-white font-black">
                            {{ $initials }}
                        </div>
                        <div class="hidden sm:block leading-none">
                            <div class="font-black text-gray-800 dark:text-white text-sm">
                                {{ $fullName }}
                            </div>
                            <div class="font-bold text-gray-500 dark:text-gray-400 text-xs mt-0.5">
                                {{ $user->email }}
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="px-4 py-2.5 rounded-full bg-gradient-to-r from-[#f27457] to-[#145454] text-white text-sm font-bold shadow-lg shadow-[#145454]/20 flex items-center gap-2 transition-all hover:brightness-110">
                            <span class="hidden sm:inline">Logout</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
@else
    <nav x-data="{ open: false }" class="sticky top-0 z-50 bg-gradient-to-r from-[#f27457] to-[#145454] border-b border-white/10">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-full bg-white/20 backdrop-blur-sm border border-white/20 flex items-center justify-center text-white font-black tracking-wide">
                        SC
                    </div>
                    <div class="text-white font-black tracking-tight">
                        spacechip
                    </div>
                </a>
            </div>

            <div class="hidden sm:flex items-center gap-3">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full flex items-center justify-center text-white font-black tracking-wide bg-white/20 border border-white/20">
                        {{ $initials }}
                    </div>
                    <div class="leading-none">
                        <div class="text-sm font-black text-white">
                            {{ $fullName }}
                        </div>
                        <div class="text-[10px] uppercase font-bold text-white/70 tracking-wider mt-0.5">
                            {{ $user->email }}
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="ms-2">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-white/15 text-white hover:bg-white/20 transition-all shadow-md border border-white/20"
                            aria-label="Logout">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </button>
                </form>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-white/80 hover:text-white hover:bg-white/10 focus:outline-none transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden bg-white dark:bg-slate-900 border-t border-gray-100 dark:border-white/10">
        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1">
            <div class="px-4">
                <div class="font-bold text-base text-gray-800 dark:text-white">
                    {{ $fullName }}
                </div>
                <div class="font-medium text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
    </nav>
@endif
