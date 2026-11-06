@extends('layouts.authenticated')

@section('pageTitle', 'Corriger un document')

@section('content')
    <div class="mb-7">
        <a href="{{ route('prestataire.documents.show', $document) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour au document</a>
        <h2 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Corriger le document</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Mettez à jour les informations demandées, puis transmettez à nouveau le document.</p>
    </div>

    <section class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5" aria-labelledby="rejection-reason-title">
        <h3 id="rejection-reason-title" class="text-sm font-semibold text-red-900">Motif du rejet</h3>
        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-red-800">{{ $document->rejection_comment ?: 'Aucun motif détaillé n’a été communiqué.' }}</p>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @include('prestataire.documents._form')
    </section>
@endsection
