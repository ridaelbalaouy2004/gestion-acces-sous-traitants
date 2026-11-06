@extends('layouts.authenticated')

@section('pageTitle', $pageTitle)

@section('content')
    <section class="flex min-h-[25rem] flex-col items-center justify-center rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-700" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" class="h-7 w-7"><path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 4v4h4M8.5 12h7M8.5 15.5h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        </span>
        <p class="mt-5 text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">{{ $roleLabel }}</p>
        <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">{{ $pageTitle }}</h2>
        <p class="mt-3 max-w-md text-sm leading-6 text-slate-600">Fonctionnalité en cours de développement. Elle sera disponible prochainement dans votre espace.</p>
        <a href="{{ route($routePrefix.'.dashboard') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-800">
            Retour au tableau de bord
        </a>
    </section>
@endsection
