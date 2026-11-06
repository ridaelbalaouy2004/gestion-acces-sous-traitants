@extends('layouts.authenticated')

@section('pageTitle', 'Notifications')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div>
                <p class="text-sm font-medium text-slate-500">Votre activité récente</p>
                <h2 class="mt-1 text-xl font-bold tracking-tight text-slate-900">Centre de notifications</h2>
                <p class="mt-2 text-sm text-slate-600">{{ $notifications->total() }} notification(s)</p>
            </div>
            @if ($unreadNotificationCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700 sm:w-auto">
                        Tout marquer comme lu
                    </button>
                </form>
            @endif
        </div>

        @if ($notificationItems->isEmpty())
            <section class="flex min-h-[20rem] flex-col items-center justify-center rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-700" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="h-7 w-7"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <h2 class="mt-5 text-xl font-bold tracking-tight text-slate-900">Aucune notification pour le moment</h2>
                <p class="mt-2 max-w-md text-sm leading-6 text-slate-600">Les nouvelles informations concernant votre espace apparaîtront ici.</p>
            </section>
        @else
            <div class="space-y-3">
                @foreach ($notificationItems as $item)
                    @php($notification = $item['notification'])
                    <article class="rounded-2xl border {{ $notification->read_at === null ? 'border-blue-200 bg-blue-50/40' : 'border-slate-200 bg-white' }} p-4 shadow-sm sm:p-5">
                        <div class="flex items-start gap-3 sm:gap-4">
                            <span class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $notification->read_at === null ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500' }}" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="text-sm font-semibold text-slate-900">{{ $item['title'] }}</h2>
                                            @if ($notification->read_at === null)
                                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800">Non lue</span>
                                            @else
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Lue</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-sm leading-6 text-slate-700">{{ $item['message'] }}</p>
                                    </div>
                                    <time class="shrink-0 text-xs text-slate-500" datetime="{{ $notification->created_at?->toIso8601String() }}">
                                        {{ $notification->created_at?->format('d/m/Y à H:i') }}
                                    </time>
                                </div>

                                @if ($item['documentTitle'] || $item['referenceNumber'])
                                    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                        @if ($item['documentUrl'])
                                            <a href="{{ $item['documentUrl'] }}" class="font-semibold text-blue-700 underline decoration-blue-200 underline-offset-2 hover:text-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">
                                                {{ $item['documentTitle'] ?? 'Consulter le document' }}
                                            </a>
                                        @elseif ($item['documentTitle'])
                                            <span class="font-semibold text-slate-800">{{ $item['documentTitle'] }}</span>
                                        @endif
                                        @if ($item['referenceNumber'])
                                            <span class="text-slate-500">Référence : {{ $item['referenceNumber'] }}</span>
                                        @endif
                                    </div>
                                @endif

                                @if ($item['prestataireName'])
                                    <p class="mt-2 text-sm text-slate-600">
                                        <span class="font-semibold">Prestataire :</span> {{ $item['prestataireName'] }}
                                    </p>
                                @endif

                                @if ($item['statusLabel'])
                                    <p class="mt-2 text-sm text-slate-600"><span class="font-semibold">Statut :</span> {{ $item['statusLabel'] }}</p>
                                @endif

                                @if ($item['memberNames'])
                                    <p class="mt-2 text-sm text-slate-600">
                                        <span class="font-semibold">Membre(s) concerné(s) :</span> {{ implode(', ', $item['memberNames']) }}
                                    </p>
                                @endif
                                @if ($item['revisionMemberName'])
                                    <p class="mt-2 text-sm text-slate-600">
                                        <span class="font-semibold">Membre concerné :</span> {{ $item['revisionMemberName'] }}
                                    </p>
                                @endif

                                @if ($item['dateLabel'])
                                    <p class="mt-2 text-xs text-slate-500">
                                        {{ $item['dateLabel'] }} {{ $notification->created_at?->format('d/m/Y à H:i') }}
                                    </p>
                                @endif

                                @if ($item['comment'])
                                    <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm leading-6 text-amber-950">
                                        <span class="font-semibold">Motif :</span> {{ $item['comment'] }}
                                    </p>
                                @endif

                                @if ($notification->read_at === null)
                                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="mt-3">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-sm font-semibold text-blue-700 underline decoration-blue-200 underline-offset-2 hover:text-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">
                                            Marquer comme lue
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div>
                {{ $notifications->links() }}
            </div>
        @endif
    </section>
@endsection
