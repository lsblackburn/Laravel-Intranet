@props(['href', 'label', 'description' => null, 'active' => false, 'icon' => 'dashboard'])

@php
    $paths = [
        'dashboard' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'calendar' => 'M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z M8 14h2 M14 14h2 M8 18h2',
        'plus' => 'M12 5v14 M5 12h14',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 3a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
        'lock' => 'M7 11V7a5 5 0 0 1 10 0v4 M5 11h14v10H5z M12 15v2',
        'briefcase' => 'M8 7V4h8v3 M3 7h18v14H3z M3 12a20 20 0 0 0 18 0 M12 11v4',
        'currency' => 'M17 18a2 2 0 01-2 2H5a3 3 0 003-3V8a4 4 0 018 0 M6 12h7'
    ];
@endphp

<a href="{{ $href }}" @if($active) aria-current="page" @endif
    class="group flex min-h-15 items-center gap-3 rounded-2xl border border-(--color-border) bg-(--color-card) p-3.5 text-(--color-text) transition-colors hover:border-[color-mix(in_srgb,var(--color-primary)_40%,var(--color-border))] hover:bg-[color-mix(in_srgb,var(--color-primary)_7%,var(--color-card))] aria-[current=page]:border-[color-mix(in_srgb,var(--color-primary)_40%,var(--color-border))] aria-[current=page]:bg-[color-mix(in_srgb,var(--color-primary)_7%,var(--color-card))]">
    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-(--color-surface-alt) text-(--color-subtletext) group-aria-[current=page]:bg-(--color-primary) group-aria-[current=page]:text-(--color-card)" aria-hidden="true">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $paths[$icon] ?? $paths['dashboard'] }}" /></svg>
    </span>
    <span class="min-w-0">
        <span class="block text-sm font-semibold">{{ $label }}</span>
        @if ($description)
            <span class="mt-1 block text-xs text-(--color-subtletext)">{{ $description }}</span>
        @endif
    </span>
    @if ($active)
        <span class="sr-only">Current page</span>
    @endif
</a>
