@if ($employee->profile_photo_path && ! ($forcePlaceholder ?? false))
    @if ($fallbackOnError ?? false)
        <span class="relative inline-flex {{ $sizeClass }} shrink-0">
    @endif
    <img
        src="{{ $photoUrl }}"
        alt="Photo de {{ $employeeName }}"
        loading="lazy"
        decoding="async"
        @if ($fallbackOnError ?? false) data-team-employee-photo @endif
        class="{{ $sizeClass }} shrink-0 rounded-full object-cover ring-2 ring-white"
    >
    @if ($fallbackOnError ?? false)
            <span hidden data-profile-photo-error-fallback class="absolute inset-0">
                @include('shared.company-employees.avatar', [
                    'employee' => $employee,
                    'employeeName' => $employeeName,
                    'photoUrl' => $photoUrl,
                    'sizeClass' => $sizeClass,
                    'forcePlaceholder' => true,
                ])
            </span>
        </span>
    @endif
@else
    <span
        data-profile-photo-placeholder
        role="img"
        aria-label="Aucune photo pour {{ $employeeName }}"
        class="{{ $sizeClass }} inline-flex shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 ring-2 ring-white"
    >
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-1/2">
            <path d="M20 21a8 8 0 0 0-16 0" stroke="currentColor" stroke-linecap="round" stroke-width="1.8"/>
            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/>
        </svg>
    </span>
@endif
