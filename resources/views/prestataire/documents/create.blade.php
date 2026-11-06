@extends('layouts.authenticated')

@section('pageTitle', 'Nouveau document')

@section('content')
    <div class="mb-7">
        <a href="{{ route('prestataire.documents.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Retour à mes documents</a>
        <h2 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Transmettre un document</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Renseignez les informations nécessaires à votre demande d’accès. Les champs marqués d’un astérisque sont obligatoires.</p>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @include('prestataire.documents._form')
    </section>
@endsection
