<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('pageTitle', 'Tableau de bord') | Gestion des Accès Sous-traitants</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell min-h-screen">
    <div class="min-h-screen md:flex">
        <div data-sidebar-overlay class="sidebar-overlay fixed inset-0 z-30 bg-slate-950/50 md:hidden" aria-hidden="true"></div>

        <aside
            id="application-sidebar"
            data-sidebar
            class="dashboard-sidebar fixed inset-y-0 left-0 z-40 flex w-[min(19rem,88vw)] flex-col border-r border-slate-200 bg-white transition-transform duration-200 md:sticky md:top-0 md:h-screen md:w-72 md:shrink-0 md:translate-x-0"
            aria-label="Navigation principale"
        >
            <div class="flex min-h-[5.25rem] items-center justify-between gap-3 border-b border-slate-100 px-5">
                <a href="{{ route($routePrefix.'.dashboard') }}" class="flex min-w-0 items-center gap-3 rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">
                    <span class="brand-mark flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
                            <path d="M12 3 5 6v5c0 4.7 2.9 8.2 7 10 4.1-1.8 7-5.3 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            <path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-bold leading-5 text-slate-900">Gestion des Accès</span>
                        <span class="block truncate text-xs leading-4 text-slate-500">Sous-traitants</span>
                    </span>
                </a>
                <button
                    type="button"
                    data-sidebar-close
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700 md:hidden"
                    aria-label="Fermer le menu"
                >
                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true">
                        <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="border-b border-slate-100 px-4 py-4">
                <div class="flex items-center gap-3 rounded-2xl bg-slate-50 p-3">
                    <span class="user-avatar flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</span>
                        <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $roleLabel }}</span>
                    </span>
                    <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500" title="Session active"></span>
                </div>
            </div>

            @php
                $sidebarNavigation = $navigation;

                if ($routePrefix === 'gestionnaire') {
                    $gestionnaireRouteOrder = [
                        'gestionnaire.dashboard',
                        'notifications.index',
                        'gestionnaire.companies.index',
                        'gestionnaire.employees.index',
                        'gestionnaire.machinery.index',
                        'gestionnaire.toolings.index',
                        'gestionnaire.prestataires.index',
                        'gestionnaire.prestataires.create',
                        'gestionnaire.employes.index',
                        'gestionnaire.delegated.index',
                        'gestionnaire.guides.required-documents.index',
                        'gestionnaire.profile',
                    ];
                    $navigationPriorities = array_flip($gestionnaireRouteOrder);

                    usort($sidebarNavigation, static fn (array $first, array $second): int => (
                        $navigationPriorities[$first['route']] ?? PHP_INT_MAX
                    ) <=> (
                        $navigationPriorities[$second['route']] ?? PHP_INT_MAX
                    ));
                }
            @endphp

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5" aria-label="Menu de l’espace {{ $roleLabel }}">
                <p class="px-3 pb-2 text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-400">Espace de travail</p>
                @foreach ($sidebarNavigation as $item)
                    @php($isCollectionRoute = str_ends_with($item['route'], '.index'))
                    @php($routePrefix = $isCollectionRoute ? substr($item['route'], 0, -5) : null)
                    @php($isActive = request()->routeIs($item['route']) || ($routePrefix !== null && request()->routeIs($routePrefix.'*')) || ($item['route'] === 'notifications.index' && request()->routeIs('prestataire.notifications.index', 'gestionnaire.notifications.index', 'responsable-securite.notifications.index')))
                    <a
                        href="{{ route($item['route']) }}"
                        @if ($isActive) aria-current="page" @endif
                        class="{{ $isActive ? 'nav-item-active' : 'nav-item' }} group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700"
                    >
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center" aria-hidden="true">
                            @switch($item['icon'])
                                @case('dashboard')
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.7"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.7"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.7"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.7"/></svg>
                                    @break
                                @case('documents')
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 4v4h4M8.5 12h7M8.5 15.5h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                                    @break
                                @case('plus')
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    @break
                                @case('clock')
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @break
                                @case('check')
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="m8.5 12 2.25 2.25 4.75-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @break
                                @case('x')
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="m9 9 6 6m0-6-6 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                                    @break
                                @case('users')
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0M16 5.5a3 3 0 0 1 0 5.8m1 2.2a5 5 0 0 1 3.5 4.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                                    @break
                                @case('building')
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><path d="M4 20V5.5A1.5 1.5 0 0 1 5.5 4H15v16M15 9h3.5A1.5 1.5 0 0 1 20 10.5V20M2.5 20h19" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 8h3m-3 4h3m-3 4h3m7-3h.01m0 3h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                                    @break
                                @case('truck')
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><path d="M3 6h11v11H3zM14 10h4l3 3v4h-7z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="7.5" cy="18" r="1.75" stroke="currentColor" stroke-width="1.7"/><circle cx="17.5" cy="18" r="1.75" stroke="currentColor" stroke-width="1.7"/></svg>
                                    @break
                                @case('tool')
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><path d="M14.5 6.5a5 5 0 0 0 6 6L12 21l-5-5 8.5-8.5ZM5 4l4 4M3 8l5-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @break
                                @case('bell')
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @break
                                @case('user')
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.7"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                                    @break
                            @endswitch
                        </span>
                        <span class="min-w-0 flex-1">{{ $item['label'] }}</span>
                        @if ($item['route'] === 'notifications.index' && $unreadNotificationCount > 0)
                            <span class="notification-count-badge shrink-0 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800" aria-label="{{ $unreadNotificationCount }} notifications non lues">{{ $unreadNotificationCount }}</span>
                        @endif
                        @if ($isActive)
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600" aria-hidden="true"></span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-slate-100 p-4">
                <div class="rounded-2xl bg-blue-50/80 p-4">
                    <p class="text-xs font-semibold text-blue-900">Besoin d’aide ?</p>
                    <p class="mt-1 text-xs leading-5 text-blue-800/80">Contactez votre administrateur pour toute question liée à votre espace.</p>
                </div>
            </div>
        </aside>

        <div class="flex min-h-screen min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur">
                <div class="flex min-h-[5.25rem] items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            data-sidebar-toggle
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700 md:hidden"
                            aria-label="Ouvrir le menu"
                            aria-controls="application-sidebar"
                            aria-expanded="false"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </button>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium text-slate-500">{{ $roleLabel }} <span class="px-1 text-slate-300">/</span> <span class="text-slate-700">@yield('pageTitle', 'Tableau de bord')</span></p>
                            <h1 class="mt-0.5 truncate text-lg font-semibold tracking-tight text-slate-900 sm:text-xl">@yield('pageTitle', 'Tableau de bord')</h1>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <a
                            href="{{ route('notifications.index') }}"
                            class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700"
                            aria-label="Notifications{{ $unreadNotificationCount > 0 ? ', '.$unreadNotificationCount.' non lues' : '' }}"
                            title="Notifications"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            @if ($unreadNotificationCount > 0)
                                <span class="notification-count-badge absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[0.65rem] font-bold leading-none text-white" aria-hidden="true">{{ $unreadNotificationCount }}</span>
                            @endif
                        </a>
                        <div class="hidden text-right sm:block">
                            <p class="max-w-40 truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500">{{ $roleLabel }}</p>
                        </div>
                        <span class="user-avatar flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold text-white" aria-hidden="true">
                            {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                        </span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700 sm:px-3.5 sm:text-sm">
                                Déconnexion
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="w-full flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    @if (session('status'))
                        <div data-server-flash-status class="mb-6 flex flex-wrap items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                            <span data-server-flash-message>{{ session('status') }}</span>
                        </div>
                    @endif
                    @if ($errors->any())
                        <div data-server-validation-errors class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                            <ul class="list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
