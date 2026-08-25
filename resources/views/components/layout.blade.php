<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SERVIREPARAR' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
    <script>
        if (localStorage.getItem('serviops.dark') === '1') {
            document.documentElement.classList.add('dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans">

@auth
    <div x-data class="min-h-screen bg-slate-100 dark:bg-slate-950">
        <div class="flex" :class="{ 'flex-col': $store.ui.navMode === 'topbar' }">

            {{-- Fondo oscuro móvil al abrir el menú lateral en pantallas pequeñas --}}
            <div x-show="$store.ui.navMode === 'sidebar' && $store.ui.mobileNavOpen" x-cloak
                 @click="$store.ui.mobileNavOpen = false"
                 class="fixed inset-0 bg-black/40 z-30 lg:hidden"></div>

            {{-- Sidebar (navegación lateral) --}}
            <aside x-show="$store.ui.navMode === 'sidebar'" x-cloak
                   :class="$store.ui.mobileNavOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                   class="w-64 shrink-0 bg-brand-navy min-h-screen p-3.5 flex flex-col gap-5 fixed inset-y-0 left-0 z-40 transition-transform duration-200 lg:static lg:z-auto">
                <div class="bg-white rounded-lg px-3 py-2 w-fit">
                    <img src="{{ asset('img/logo.png') }}" alt="ServiReparar" class="h-[22px] w-auto block">
                </div>
                <nav class="flex flex-col gap-1">
                    @include('partials.nav-items', ['variant' => 'sidebar'])
                </nav>
                <div class="mt-auto flex items-center gap-2.5 bg-brand-navy-soft rounded-xl px-2.5 py-2.5">
                    <div class="w-8 h-8 rounded-full bg-brand-blue text-white flex items-center justify-center text-xs font-bold shrink-0">
                        {{ collect(explode(' ', auth()->user()->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                    </div>
                    <div class="min-w-0">
                        <div class="text-[12.5px] font-semibold text-white truncate">{{ auth()->user()->name }}</div>
                        <div class="text-[10.5px] text-slate-400 truncate">{{ auth()->user()->getRoleNames()->first() ?? 'Sin rol' }}</div>
                    </div>
                </div>
            </aside>

            <div class="flex-1 min-w-0 flex flex-col">

                {{-- Topbar --}}
                <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-6 h-16 flex items-center gap-3">

                    <button @click="$store.ui.toggleMobileNav()"
                            x-show="$store.ui.navMode === 'sidebar'" x-cloak
                            class="lg:hidden w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-300 shrink-0"
                            title="Abrir menú">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>

                    <template x-if="$store.ui.navMode === 'topbar'">
                        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 w-fit shrink-0">
                            <img src="{{ asset('img/logo.png') }}" alt="ServiReparar" class="h-4 w-auto block">
                        </div>
                    </template>

                    @if ($title ?? null)
                        <h1 class="text-[17px] font-bold font-display truncate min-w-0">{{ $title }}</h1>
                    @endif

                    <nav x-show="$store.ui.navMode === 'topbar'" x-cloak
                         class="flex items-center gap-1 overflow-x-auto ml-1 [scrollbar-width:none]">
                        @include('partials.nav-items', ['variant' => 'topbar'])
                    </nav>

                    <div class="flex items-center gap-2 sm:gap-3 shrink-0 ml-auto">
                            {{-- Preferencias de apariencia --}}
                            <div x-data="{ open: false }" class="relative">
                                <button @click="open = !open" @click.outside="open = false"
                                        class="w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                                        title="Preferencias de apariencia">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <circle cx="12" cy="12" r="3.2"/>
                                        <path d="M19.4 13.5a1.7 1.7 0 000-3l-1-.6a7.5 7.5 0 00-.7-1.7l.4-1.1a1.7 1.7 0 00-2.1-2.1l-1.1.4a7.5 7.5 0 00-1.7-.7l-.6-1a1.7 1.7 0 00-3 0l-.6 1a7.5 7.5 0 00-1.7.7l-1.1-.4a1.7 1.7 0 00-2.1 2.1l.4 1.1a7.5 7.5 0 00-.7 1.7l-1 .6a1.7 1.7 0 000 3l1 .6a7.5 7.5 0 00.7 1.7l-.4 1.1a1.7 1.7 0 002.1 2.1l1.1-.4a7.5 7.5 0 001.7.7l.6 1a1.7 1.7 0 003 0l.6-1a7.5 7.5 0 001.7-.7l1.1.4a1.7 1.7 0 002.1-2.1l-.4-1.1a7.5 7.5 0 00.7-1.7z"/>
                                    </svg>
                                </button>

                                <div x-show="open" x-cloak @click.stop
                                     class="absolute right-0 mt-2 w-72 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg p-4 z-30">
                                    <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-3">Diseño del sitio</div>

                                    <div class="flex items-center justify-between mb-4">
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Modo oscuro</span>
                                        <button @click="$store.ui.toggleDark()"
                                                class="w-10 h-6 rounded-full transition relative shrink-0"
                                                :class="$store.ui.dark ? 'bg-brand-blue' : 'bg-slate-300 dark:bg-slate-600'">
                                            <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform"
                                                  :class="{ 'translate-x-4': $store.ui.dark }"></span>
                                        </button>
                                    </div>

                                    <div>
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200 block mb-2">Menú de navegación</span>
                                        <div class="grid grid-cols-2 gap-2">
                                            <button @click="$store.ui.setNavMode('sidebar')"
                                                    class="flex flex-col items-center gap-1.5 rounded-lg border py-2.5 text-xs font-semibold transition"
                                                    :class="$store.ui.navMode === 'sidebar' ? 'border-brand-blue bg-brand-blue-tint text-brand-blue dark:bg-brand-navy-active dark:text-white' : 'border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:border-slate-300'">
                                                <svg width="20" height="16" viewBox="0 0 20 16" fill="none"><rect x="0.5" y="0.5" width="6" height="15" rx="1" fill="currentColor"/><rect x="8" y="0.5" width="11.5" height="15" rx="1" stroke="currentColor"/></svg>
                                                Lateral
                                            </button>
                                            <button @click="$store.ui.setNavMode('topbar')"
                                                    class="flex flex-col items-center gap-1.5 rounded-lg border py-2.5 text-xs font-semibold transition"
                                                    :class="$store.ui.navMode === 'topbar' ? 'border-brand-blue bg-brand-blue-tint text-brand-blue dark:bg-brand-navy-active dark:text-white' : 'border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:border-slate-300'">
                                                <svg width="20" height="16" viewBox="0 0 20 16" fill="none"><rect x="0.5" y="0.5" width="19" height="5" rx="1" fill="currentColor"/><rect x="0.5" y="6.5" width="19" height="9" rx="1" stroke="currentColor"/></svg>
                                                Superior
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="w-px h-6 bg-slate-200 dark:bg-slate-700"></div>

                            <div class="flex items-center gap-2.5">
                                <a href="{{ route('perfil') }}" title="Mi perfil" class="flex items-center gap-2.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 px-1.5 py-1 -mx-1.5">
                                    <div class="w-8 h-8 rounded-full bg-brand-blue-tint dark:bg-brand-navy-active text-brand-blue dark:text-white flex items-center justify-center text-xs font-bold">
                                        {{ collect(explode(' ', auth()->user()->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                                    </div>
                                    <div class="hidden sm:block leading-tight">
                                        <div class="text-[13px] font-semibold text-slate-800 dark:text-slate-100">{{ auth()->user()->name }}</div>
                                        <div class="text-[11px] text-slate-400">{{ auth()->user()->getRoleNames()->first() ?? 'Sin rol' }}</div>
                                    </div>
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" title="Salir" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-400 hover:text-brand-red hover:border-brand-red-tint">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a1 1 0 01-1-1V4a1 1 0 011-1h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
                                    </button>
                                </form>
                            </div>
                    </div>
                </header>

                <main class="flex-1 p-4 sm:p-7">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </div>
@else
    <div class="min-h-screen">
        {{ $slot }}
    </div>
@endauth

@livewireScripts
</body>
</html>
