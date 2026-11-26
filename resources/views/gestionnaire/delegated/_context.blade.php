<section class="overflow-hidden rounded-2xl border border-[#d9eee2] bg-[#fffefa] shadow-sm">
    <div class="p-4 sm:px-6 sm:py-5">
        <nav aria-label="Fil d’Ariane" class="mb-4">
            <ol class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <li>
                    <a href="{{ route('gestionnaire.delegated.index') }}" class="rounded font-medium transition hover:text-[#164f43] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]">
                        Gestion déléguée
                    </a>
                </li>
                <li aria-hidden="true">/</li>
                <li>
                    <a href="{{ route('gestionnaire.delegated.show', $prestataire) }}" class="max-w-56 truncate rounded font-medium transition hover:text-[#164f43] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]" title="{{ $prestataire->name }}">
                        {{ $prestataire->name }}
                    </a>
                </li>
                @if (isset($company))
                    <li aria-hidden="true">/</li>
                    <li>
                        <a href="{{ route('gestionnaire.delegated.companies.show', [$prestataire, $company]) }}" class="max-w-56 truncate rounded font-medium transition hover:text-[#164f43] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43]" title="{{ $company->name }}">
                            {{ $company->name }}
                        </a>
                    </li>
                @endif
                @if (filled($currentLabel ?? null))
                    <li aria-hidden="true">/</li>
                    <li aria-current="page" class="font-semibold text-[#164f43]">{{ $currentLabel }}</li>
                @endif
            </ol>
        </nav>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#d9eee2] text-[#164f43]" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
                        <circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/>
                        <path d="M3.5 19a5.5 5.5 0 0 1 11 0M16 5.5a3 3 0 0 1 0 5.8m1 2.2a5 5 0 0 1 3.5 4.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-slate-500">Vous effectuez ces opérations pour le compte de</p>
                    <p class="truncate font-semibold text-[#123e36]" title="{{ $prestataire->name }}">{{ $prestataire->name }}</p>
                    @if (isset($company) && ($showCompanyInContext ?? true))
                        <p class="mt-0.5 truncate text-xs text-slate-500" title="{{ $company->name }}">Entreprise : {{ $company->name }}</p>
                    @endif
                </div>
            </div>
            @if (filled($backUrl ?? null))
                <a href="{{ $backUrl }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 self-start rounded-xl border border-[#c7e4d3] bg-white px-4 py-2 text-sm font-semibold text-[#164f43] transition hover:bg-[#d9eee2] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#164f43] sm:self-auto">
                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                        <path d="M19 12H5m0 0 6 6m-6-6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    {{ $backLabel ?? 'Retour' }}
                </a>
            @endif
        </div>
    </div>
    <div class="h-1 bg-[#164f43]" aria-hidden="true"></div>
</section>
