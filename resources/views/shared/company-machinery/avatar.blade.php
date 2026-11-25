@if ($machinery->photo_path && ! ($forcePlaceholder ?? false))
    @if ($fallbackOnError ?? false)
        <span class="relative inline-flex {{ $sizeClass }} shrink-0">
    @endif
    <img
        src="{{ $photoUrl }}"
        alt="Photo de {{ $machinery->designation }}"
        loading="lazy"
        decoding="async"
        @if ($fallbackOnError ?? false) data-company-machinery-photo @endif
        class="{{ $sizeClass }} shrink-0 rounded-full object-cover ring-2 ring-white"
    >
    @if ($fallbackOnError ?? false)
            <span hidden data-machinery-photo-error-fallback class="absolute inset-0">
                @include('shared.company-machinery.avatar', [
                    'machinery' => $machinery,
                    'photoUrl' => $photoUrl,
                    'sizeClass' => $sizeClass,
                    'forcePlaceholder' => true,
                ])
            </span>
        </span>
    @endif
@else
    <span
        data-machinery-photo-placeholder
        role="img"
        aria-label="Aucune photo pour {{ $machinery->designation }}"
        class="{{ $sizeClass }} inline-flex shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 ring-2 ring-white"
    >
        <svg aria-hidden="true" data-machinery-tractor-icon viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-1/2">
            <path d="m10 11 11 .9a1 1 0 0 1 .8 1.1l-.665 4.158a1 1 0 0 1-.988.842H20"/>
            <path d="M16 18h-5"/>
            <path d="M18 5a1 1 0 0 0-1 1v5.573"/>
            <path d="M3 4h8.129a1 1 0 0 1 .99.863L13 11.246"/>
            <path d="M4 11V4"/>
            <path d="M7 15h.01"/>
            <path d="M8 10.1V4"/>
            <circle cx="18" cy="18" r="2"/>
            <circle cx="7" cy="15" r="5"/>
        </svg>
    </span>
@endif
