@if ($tooling->photo_path && ! ($forcePlaceholder ?? false))
    @if ($fallbackOnError ?? false)
        <span class="relative inline-flex {{ $sizeClass }} shrink-0">
    @endif
    <img
        src="{{ $photoUrl }}"
        alt="Photo de {{ $tooling->designation }}"
        loading="lazy"
        decoding="async"
        @if ($fallbackOnError ?? false) data-company-tooling-photo @endif
        class="{{ $sizeClass }} shrink-0 rounded-full object-cover ring-2 ring-white"
    >
    @if ($fallbackOnError ?? false)
            <span hidden data-tooling-photo-error-fallback class="absolute inset-0">
                @include('shared.company-toolings.avatar', [
                    'tooling' => $tooling,
                    'photoUrl' => $photoUrl,
                    'sizeClass' => $sizeClass,
                    'forcePlaceholder' => true,
                ])
            </span>
        </span>
    @endif
@else
    <span
        data-tooling-photo-placeholder
        role="img"
        aria-label="Aucune photo pour {{ $tooling->designation }}"
        class="{{ $sizeClass }} inline-flex shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 ring-2 ring-white"
    >
        <svg aria-hidden="true" data-tooling-wrench-icon viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-1/2">
            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/>
        </svg>
    </span>
@endif
