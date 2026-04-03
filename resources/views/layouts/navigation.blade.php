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
    <nav class="border-b border-gray-100" style="background: rgba(255,255,255,.78); backdrop-filter: blur(12px);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <div style="height:40px;width:40px;border-radius:9999px;background:linear-gradient(90deg,#f27457,#145454);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;letter-spacing:.02em">
                        SC
                    </div>
                    <div style="font-weight:800;color:rgba(20,84,84,.92);letter-spacing:.02em">
                        spacechip
                    </div>
                </a>

                <div class="flex items-center gap-3">
                    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:16px;border:1px solid rgba(255,255,255,.22);backdrop-filter:blur(10px)">
                        <div style="height:44px;width:44px;border-radius:9999px;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.20);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;letter-spacing:.02em">
                            {{ $initials }}
                        </div>
                        <div style="line-height:1.15">
                            <div style="font-weight:900;color:#194436;font-size:14px">
                                {{ $fullName }}
                            </div>
                            <div style="font-weight:650;color:#194436;font-size:12px">
                                {{ $user->email }}
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" style="padding:10px 16px;border-radius:9999px;background:linear-gradient(90deg,#f27457,#145454);color:#fff;font-size:14px;font-weight:700;box-shadow:0 14px 35px rgba(20,84,84,.14),0 2px 6px rgba(0,0,0,.06);text-decoration:none;display:inline-flex;align-items:center;gap:8px">
                            Logout
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
    <nav x-data="{ open: false }" class="bg-white border-b border-gray-100" style="background: radial-gradient(700px 220px at 18% 0%, rgba(255,255,255,.22) 0%, rgba(255,255,255,0) 60%), radial-gradient(560px 240px at 82% 100%, rgba(0,0,0,.16) 0%, rgba(0,0,0,0) 62%), linear-gradient(90deg, #f27457, #145454);">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-white font-extrabold tracking-wide">
                        SC
                    </div>
                    <div class="text-white font-extrabold tracking-tight">
                        spacechip
                    </div>
                </a>
            </div>

            <div class="hidden sm:flex items-center gap-3">
                <div class="flex items-center gap-3">
                    <div class="h-11 w-11 rounded-full flex items-center justify-center text-white font-extrabold tracking-wide"
                         style="background: rgba(242,116,87,.35); border: 1px solid rgba(255,255,255,.22);">
                        {{ $initials }}
                    </div>
                    <div class="leading-tight">
                        <div class="text-sm font-extrabold text-white">
                            {{ $fullName }}
                        </div>
                        <div class="text-xs text-white/80">
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
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">
                    {{ $fullName }}
                </div>
                <div class="font-medium text-sm text-gray-500">{{ $user->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
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
